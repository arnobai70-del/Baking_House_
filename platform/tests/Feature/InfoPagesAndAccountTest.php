<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CrmLead;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Once;
use Tests\TestCase;

/**
 * Phase 8 additions: owner-supplied information pages, contact & support, profile / password, and
 * the staff & roles screen.
 */
class InfoPagesAndAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_pages_are_public_only_when_published_and_content_is_sanitised(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAsStaff($admin)->post('/admin/pages', [
            'slug' => 'delivery-policy', 'title' => 'Delivery policy', 'title_bn' => 'ডেলিভারি নীতি',
            'body' => "## Delivery\n\nWe deliver in Dhaka.<script>alert(1)</script>\n\n[bad](javascript:alert(1)) [good](https://example.com)",
        ])->assertRedirect();
        $page = Page::query()->sole();

        $this->get('/page/delivery-policy')->assertNotFound();
        $this->get('/')->assertSee('Delivery policy — pending');
        $this->get('/sitemap.xml')->assertDontSee('/page/delivery-policy');

        $this->actingAsStaff($admin)->put(route('admin.pages.update', $page), ['slug' => 'delivery-policy', 'title' => 'Delivery policy', 'body' => $page->body, 'is_published' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['action' => 'page.published']);

        $response = $this->get('/page/delivery-policy')->assertOk()
            ->assertSee('<h2>Delivery</h2>', false)->assertSee('We deliver in Dhaka.')->assertSee('href="https://example.com"', false)
            ->assertSee('<link rel="canonical" href="'.route('pages.show', 'delivery-policy').'">', false);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
        $this->assertStringNotContainsString('javascript:alert', $response->getContent());
        Once::flush(); /* each real request starts with an empty memo */
        $this->get('/')->assertSee(route('pages.show', 'delivery-policy'))->assertDontSee('Delivery policy — pending');
        $this->get('/sitemap.xml')->assertSee('/page/delivery-policy');
    }

    public function test_page_slugs_are_validated_and_pages_need_the_cms_permission(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAsStaff($admin)->post('/admin/pages', ['slug' => '../etc/passwd', 'title' => 'x', 'body' => 'y'])->assertSessionHasErrors('slug');

        $customer = User::factory()->create();
        $this->actingAs($customer)->get('/admin/pages')->assertForbidden();
        $this->actingAs($customer)->post('/admin/pages', ['slug' => 'about-us', 'title' => 'x', 'body' => 'y'])->assertForbidden();
        $this->assertSame(0, Page::query()->count());
    }

    public function test_contact_messages_go_to_the_shared_crm_queue(): void
    {
        $this->get('/contact')->assertOk()->assertSee('Phone numbers, address and opening hours will be published after the owner verifies them.');

        $this->post('/contact', ['name' => 'Rina', 'phone' => '0171', 'topic' => 'order', 'message' => 'Hello'])->assertSessionHasErrors('phone');
        $this->post('/contact', ['name' => 'Bot', 'phone' => '01711111111', 'topic' => 'other', 'message' => 'Spam text', 'website' => 'x'])->assertSessionHas('status');
        $this->assertSame(0, CrmLead::query()->count());

        $this->post('/contact', ['name' => 'Rina', 'phone' => '+880 1711-111111', 'topic' => 'order', 'order_number' => 'bh261120-abc123', 'message' => 'Where is my order?'])
            ->assertRedirect(route('contact'));

        $lead = CrmLead::query()->sole();
        $this->assertSame('contact', $lead->source->value);
        $this->assertNull($lead->assigned_to, 'Shared queue.');
        $this->assertSame('01711111111', $lead->contact_phone);
        $this->assertStringContainsString('BH261120-ABC123', $lead->title);
        $this->assertStringContainsString('Where is my order?', $lead->crmActivities()->sole()->body);
    }

    public function test_profile_update_validates_unique_phone_and_email(): void
    {
        $other = User::factory()->create(['phone' => '01800000000', 'email' => 'taken@example.com']);
        $user = User::factory()->create(['phone' => '01700000000', 'email' => 'me@example.com']);

        $this->actingAs($user)->put('/account/profile', ['name' => 'Me', 'phone' => '01800000000', 'email' => 'me@example.com'])->assertSessionHasErrors('phone');
        $this->actingAs($user)->put('/account/profile', ['name' => 'Me', 'phone' => '01700000000', 'email' => 'taken@example.com'])->assertSessionHasErrors('email');
        $this->actingAs($user)->put('/account/profile', ['name' => 'Me Renamed', 'phone' => '+880 1755-555555', 'email' => 'new@example.com'])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame(['Me Renamed', '01755555555', 'new@example.com'], [$user->name, $user->phone, $user->email]);
        $this->assertNull($user->email_verified_at, 'A changed email must be verified again.');
        $this->assertSame('01800000000', $other->fresh()->phone);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.profile_updated', 'subject_id' => $user->id]);
    }

    public function test_password_change_needs_the_current_password_and_revokes_api_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('phone-app');

        $this->actingAs($user)->put('/account/password', ['current_password' => 'wrong', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->assertSessionHasErrors('current_password');
        $this->actingAs($user)->put('/account/password', ['current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertSessionHasErrors('password');
        $this->assertSame(1, $user->tokens()->count());

        $this->actingAs($user)->put('/account/password', ['current_password' => 'password', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('new-password-1', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'user.password_changed')->count());
        $this->assertStringNotContainsString('new-password-1', (string) AuditLog::query()->where('action', 'user.password_changed')->sole()->toJson());
    }

    public function test_staff_screen_is_super_admin_only_and_uses_the_audited_role_change(): void
    {
        $super = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create(['name' => 'Admin Rahim']);
        $customer = User::factory()->create(['email' => 'future-staff@example.com']);

        $this->actingAsStaff($admin)->get('/admin/staff')->assertForbidden();
        $this->actingAsStaff($super)->get('/admin/staff')->assertOk()->assertSee('Admin Rahim')->assertSee('reports.financial')->assertSee('Finance (financial reports)');
        $this->actingAsStaff($super)->get('/admin/staff?q=future-staff@example.com')->assertOk()->assertSee($customer->name);

        $this->actingAsStaff($super)->put(route('admin.users.roles', $admin), ['roles' => ['admin', 'finance'], 'reason' => 'Needs monthly revenue figures'])->assertSessionHasNoErrors();
        $this->assertTrue($admin->fresh()->can('reports.financial'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.roles_changed', 'reason' => 'Needs monthly revenue figures']);

        $this->actingAsStaff($super)->put(route('admin.users.roles', $super), ['roles' => [], 'reason' => 'Remove my own roles'])->assertForbidden();
    }

    public function test_robots_header_blocks_everything_until_indexing_and_private_paths_after(): void
    {
        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/robots.txt')->assertSee('Disallow: /');

        config(['seo.indexing_enabled' => true]);
        $this->get('/')->assertHeaderMissing('X-Robots-Tag');
        $this->get('/business')->assertHeaderMissing('X-Robots-Tag');
        $this->get('/contact')->assertHeaderMissing('X-Robots-Tag');
        $this->get('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/business/dashboard')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/admin')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/robots.txt')->assertSee('Disallow: /business/')->assertDontSee('Disallow: /business
', false)->assertSee('Sitemap:');
    }
}
