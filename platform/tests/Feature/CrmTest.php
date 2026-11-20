<?php

namespace Tests\Feature;

use App\Enums\ActivityType;
use App\Enums\LeadStage;
use App\Enums\PaymentMethod;
use App\Models\CrmActivity;
use App\Models\CrmLead;
use App\Models\CrmTask;
use App\Models\NotificationLog;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\PriceList;
use App\Models\Role;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Crm\CrmService;
use App\Services\Orders\OrderService;
use App\Services\Pricing\PricingContext;
use App\Services\Quotations\QuotationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Support\CommerceHelpers;
use Tests\Support\PortalHelpers;
use Tests\TestCase;

/**
 * CRM: shared RFQ queue, assignment, pipeline, timeline, tasks, tags, segments and access limits.
 */
class CrmTest extends TestCase
{
    use CommerceHelpers, PortalHelpers, RefreshDatabase;

    private User $admin;

    private User $sales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zones();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->admin()->create(['name' => 'Admin Rina']);
        $role = Role::create(['name' => 'sales', 'label' => 'Sales']);
        $role->permissions()->sync(Permission::query()->whereIn('name', ['admin.access', 'crm.access'])->pluck('id'));
        $this->sales = User::factory()->withMfa()->withRole('sales')->create(['name' => 'Sales Karim']);
    }

    public function test_every_rfq_enters_the_shared_queue_until_someone_takes_it(): void
    {
        $this->post('/quotations', ['contact_name' => 'Guest Chef', 'contact_phone' => '01711111111', 'request_text' => "Deck oven × 1\nTrays × 20"]);
        $lead = CrmLead::query()->sole();

        $this->assertSame('rfq', $lead->source->value);
        $this->assertNull($lead->assigned_to);
        $this->assertTrue($lead->isInSharedQueue());
        $this->assertStringContainsString('Deck oven × 1', $lead->title);
        $this->actingAsStaff($this->sales)->get('/admin/crm')->assertOk()->assertSee('Shared queue')->assertSee('Deck oven');

        $this->actingAsStaff($this->sales)->post(route('admin.crm.leads.assign', $lead), ['assigned_to' => 'me'])->assertSessionHasNoErrors();
        $this->assertSame($this->sales->id, $lead->fresh()->assigned_to);
        $this->assertDatabaseHas('audit_logs', ['action' => 'crm.lead_assigned', 'actor_id' => $this->sales->id]);
        $this->assertSame(0, CrmLead::query()->whereNull('assigned_to')->count());
    }

    public function test_two_people_cannot_both_claim_the_same_queued_lead(): void
    {
        $this->post('/quotations', ['contact_name' => 'Guest', 'contact_phone' => '01711111111', 'request_text' => 'Oven']);
        $lead = CrmLead::query()->sole();
        $stale = $lead->replicate()->forceFill(['id' => $lead->id]);
        $stale->exists = true;

        app(CrmService::class)->assign($lead, $this->sales, $this->sales);

        $this->expectException(ValidationException::class);
        app(CrmService::class)->assign($stale, $this->admin, $this->admin);
    }

    public function test_leads_can_only_go_to_staff_with_crm_access(): void
    {
        $lead = app(CrmService::class)->createLead(['title' => 'Walk-in', 'source' => 'walk_in', 'contact_name' => null, 'contact_phone' => null, 'contact_email' => null, 'organization_id' => null, 'user_id' => null, 'estimated_value_paisa' => null], $this->admin, false);
        $customer = User::factory()->create();

        $this->actingAsStaff($this->admin)->post(route('admin.crm.leads.assign', $lead), ['assigned_to' => (string) $customer->id])->assertSessionHasErrors('assigned_to');
        $this->assertNull($lead->fresh()->assigned_to);
    }

    public function test_quotation_events_move_the_rfq_lead_through_the_pipeline(): void
    {
        [$organization, $owner] = $this->businessWith();
        $this->unitVariant(100000, 10, 'OVEN');
        $quotation = $this->issuedQuotation($organization, $owner, [['sku' => 'OVEN', 'quantity' => '1', 'price' => '90000']], $this->admin);
        $lead = CrmLead::query()->where('quotation_id', $quotation->id)->sole();
        $this->assertSame(LeadStage::Quoted, $lead->stage);
        $this->assertSame(9000000, $lead->estimated_value_paisa);
        $this->assertSame($organization->id, $lead->organization_id);

        app(QuotationService::class)->accept($quotation, $owner, $organization);
        $context = PricingContext::forOrganization($organization, $owner->id);
        app(CartService::class)->addQuotation($cart = app(CartService::class)->forUser($owner), $quotation->fresh(), $context);
        $order = $this->place($cart, PaymentMethod::BankTransfer, $owner, $context)->order;
        $this->assertSame(LeadStage::Won, $lead->fresh()->stage);
        $this->assertNotNull($lead->fresh()->closed_at);

        app(OrderService::class)->cancel($order, 'Changed plans', $owner, byCustomer: true);
        $this->assertSame(LeadStage::Quoted, $lead->fresh()->stage, 'A reopened quote reopens the lead.');

        app(QuotationService::class)->decline($quotation->fresh(), $owner, $organization, 'Too expensive');
        $this->assertSame(LeadStage::Lost, $lead->fresh()->stage);
        $this->assertSame(['system'], $lead->crmActivities()->pluck('type')->map->value->unique()->values()->all());
        $this->assertGreaterThanOrEqual(5, $lead->crmActivities()->count());
    }

    public function test_business_applications_become_leads(): void
    {
        $applicant = User::factory()->create(['phone' => '01733333333']);
        $this->actingAs($applicant)->post('/apply', ['type' => 'business', 'name' => 'Spice Garden', 'business_category' => 'Restaurant', 'contact_phone' => '01733333333'])->assertRedirect();

        $lead = CrmLead::query()->sole();
        $this->assertSame('application', $lead->source->value);
        $this->assertSame(Organization::query()->sole()->id, $lead->organization_id);
    }

    public function test_stage_changes_timeline_and_lost_reason(): void
    {
        $this->actingAsStaff($this->sales)->post(route('admin.crm.leads.store'), ['title' => 'Hotel kitchen fit-out', 'source' => 'phone', 'contact_phone' => '+8801755555555', 'estimated_value' => '250,000', 'assign_to_me' => 1])
            ->assertSessionHasNoErrors();
        $lead = CrmLead::query()->sole();
        $this->assertSame('01755555555', $lead->contact_phone);
        $this->assertSame(25000000, $lead->estimated_value_paisa);
        $this->assertSame($this->sales->id, $lead->assigned_to);

        $this->actingAsStaff($this->sales)->post(route('admin.crm.leads.stage', $lead), ['stage' => 'lost'])->assertSessionHasErrors('reason');
        $this->actingAsStaff($this->sales)->post(route('admin.crm.leads.stage', $lead), ['stage' => 'qualified'])->assertSessionHasNoErrors();
        $this->actingAsStaff($this->sales)->post(route('admin.crm.activities.store', ['lead', $lead->id]), ['type' => 'call', 'body' => 'Spoke to the chef; needs 2 ovens by March.'])->assertSessionHasNoErrors();
        $this->actingAsStaff($this->sales)->post(route('admin.crm.activities.store', ['lead', $lead->id]), ['type' => 'system', 'body' => 'Forged'])->assertSessionHasErrors('type');

        $this->actingAsStaff($this->sales)->get(route('admin.crm.leads.show', $lead))->assertOk()->assertSee('needs 2 ovens by March')->assertSee('Stage changed from New to Qualified');

        $this->expectException(LogicException::class);
        CrmActivity::query()->where('type', ActivityType::Call->value)->sole()->forceFill(['body' => 'edited'])->save();
    }

    public function test_follow_up_tasks_remind_once_and_set_the_next_follow_up(): void
    {
        $lead = app(CrmService::class)->createLead(['title' => 'Bakery chain', 'source' => 'referral', 'contact_name' => null, 'contact_phone' => null, 'contact_email' => null, 'organization_id' => null, 'user_id' => null, 'estimated_value_paisa' => null], $this->sales, true);

        $due = now('Asia/Dhaka')->addHour();
        $this->actingAsStaff($this->sales)->post(route('admin.crm.tasks.store', ['lead', $lead->id]), ['title' => 'Send catalog', 'due_at' => $due->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $task = CrmTask::query()->sole();
        $this->assertSame($this->sales->id, $task->assigned_to);
        $this->assertEquals($task->due_at->getTimestamp(), Carbon::parse($lead->fresh()->next_follow_up_at)->getTimestamp());

        $this->artisan('crm:send-reminders')->expectsOutputToContain('Sent 0');
        $this->travel(2)->hours();
        $this->artisan('crm:send-reminders')->expectsOutputToContain('Sent 1');
        $this->artisan('crm:send-reminders')->expectsOutputToContain('Sent 0');
        $this->assertSame(1, NotificationLog::query()->where('template', 'crm_task_due')->count());

        $this->actingAsStaff($this->sales)->post(route('admin.crm.tasks.complete', $task))->assertSessionHasNoErrors();
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertNull($lead->fresh()->next_follow_up_at);
    }

    public function test_segments_filter_by_type_tag_orders_and_inactivity(): void
    {
        [$organization, $owner] = $this->businessWith();
        $repeat = User::factory()->create(['name' => 'Repeat Retail']);
        $once = User::factory()->create(['name' => 'One Order']);
        $pan = $this->unitVariant(100000, 50, 'PAN');
        $this->place($this->cartWith([[$pan, 1]], $repeat), user: $repeat);
        $this->place($this->cartWith([[$pan, 1]], $repeat), user: $repeat);
        $this->place($this->cartWith([[$pan, 1]], $once), user: $once);
        $this->place($this->cartWith([[$pan, 1]], $owner), user: $owner, context: PricingContext::forOrganization($organization, $owner->id));
        app(CrmService::class)->tag($repeat, ['VIP', ' vip ', 'Chattogram'], $this->admin);

        $this->actingAsStaff($this->admin)->get('/admin/crm/customers?min_orders=2')->assertOk()->assertSee('Repeat Retail')->assertDontSee('One Order');
        $this->actingAsStaff($this->admin)->get('/admin/crm/customers?type=business')->assertSee($owner->name)->assertDontSee('Repeat Retail');
        $this->actingAsStaff($this->admin)->get('/admin/crm/customers?tag=vip')->assertSee('Repeat Retail')->assertDontSee('One Order');
        $this->assertSame(['chattogram', 'vip'], $repeat->crmTags()->pluck('name')->all());

        $this->travel(40)->days();
        $this->actingAsStaff($this->admin)->get('/admin/crm/customers?inactive_days=30')->assertSee('One Order');
        $this->actingAsStaff($this->admin)->get('/admin/crm/customers?inactive_days=60')->assertDontSee('One Order');

        $csv = $this->actingAsStaff($this->admin)->get('/admin/crm/customers?tag=vip&format=csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Repeat Retail', $csv);
        $this->assertStringContainsString('Spent (BDT)', $csv);
        $this->assertStringContainsString('2160', $csv, 'Two orders of ৳1,080.');
    }

    public function test_crm_only_staff_see_no_money_prices_or_staff_profiles(): void
    {
        [$organization, $owner] = $this->businessWith();
        $pan = $this->unitVariant(100000, 10, 'PAN');
        PriceList::factory()->contractFor($organization)->withTiers($pan, [[1, 77700]])->create(['name' => 'Secret contract list']);
        $this->place($this->cartWith([[$pan, 1]], $owner), user: $owner, context: PricingContext::forOrganization($organization, $owner->id));

        $this->actingAsStaff($this->sales)->get(route('admin.crm.organizations.show', $organization))->assertOk()
            ->assertDontSee('Secret contract list')->assertDontSee('৳777', false)->assertSee('Needs financial permission');
        $this->actingAsStaff($this->sales)->get(route('admin.crm.customers.show', $owner))->assertOk()->assertDontSee('৳857', false)->assertSee('Needs financial permission');
        $csv = $this->actingAsStaff($this->sales)->get('/admin/crm/customers?format=csv')->streamedContent();
        $this->assertStringNotContainsString('Spent', $csv);

        $this->actingAsStaff($this->sales)->get(route('admin.crm.customers.show', $this->admin))->assertNotFound();
        $this->actingAsStaff($this->sales)->post(route('admin.crm.activities.store', ['customer', $this->admin->id]), ['type' => 'note', 'body' => 'x'])->assertNotFound();
        foreach (['/admin/orders', '/admin/price-lists', '/admin/reports', '/admin/organizations', '/admin/media'] as $url) {
            $this->actingAsStaff($this->sales)->get($url)->assertForbidden();
        }

        $this->actingAs($owner)->get('/admin/crm')->assertForbidden();
        $this->actingAsStaff(User::factory()->withMfa()->withRole('sales')->create(['is_active' => false]))->get('/admin/crm')->assertStatus(302);
    }
}
