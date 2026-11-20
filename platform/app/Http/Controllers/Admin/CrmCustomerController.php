<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityType;
use App\Enums\OrderStatus;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Enums\StaffPermission;
use App\Http\Controllers\Controller;
use App\Models\CrmLead;
use App\Models\CrmTag;
use App\Models\CrmTask;
use App\Models\Organization;
use App\Models\User;
use App\Services\Crm\CrmService;
use App\Support\CsvDownload;
use App\Support\Taka;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CRM customer & organization profiles, segmentation, and the timeline / task / tag actions
 * shared by leads, customers and organizations (crm.access).
 *
 * What CRM users never see here: organization price lists or contract prices, payment details,
 * MFA / security data, or staff accounts. Order money (lifetime spend, order totals) is shown only
 * to staff who also hold reports.financial or orders.manage.
 */
class CrmCustomerController extends Controller
{
    public function __construct(private CrmService $crm) {}

    public function segments(Request $request): View|StreamedResponse
    {
        $filters = $this->filters($request);
        $canSeeMoney = $this->canSeeMoney($request);
        $query = $this->segmentQuery($filters, $canSeeMoney);

        if ($request->query('format') === 'csv') {
            $rows = [['Name', 'Email', 'Phone', 'Type', 'Organizations', 'Orders', ...($canSeeMoney ? ['Spent (BDT)'] : []), 'Last order', 'Tags', 'Joined']];
            foreach ($query->limit(5000)->get() as $customer) {
                $rows[] = [
                    $customer->name, $customer->email, $customer->phone, CrmService::customerType($customer),
                    $customer->organizations->pluck('name')->implode('; '), $customer->orders_count,
                    ...($canSeeMoney ? [Taka::toInput((int) $customer->orders_sum_total_paisa)] : []),
                    $customer->orders_max_placed_at ? Carbon::parse($customer->orders_max_placed_at)->timezone('Asia/Dhaka')->format('Y-m-d') : '',
                    $customer->crmTags->pluck('name')->implode('; '), $customer->created_at->timezone('Asia/Dhaka')->format('Y-m-d'),
                ];
            }

            return CsvDownload::response('crm-segment-'.now('Asia/Dhaka')->format('Y-m-d').'.csv', $rows);
        }

        return view('admin.crm.segments', [
            'customers' => $query->paginate(30)->withQueryString(),
            'filters' => $filters,
            'tags' => CrmTag::query()->orderBy('name')->pluck('name'),
            'canSeeMoney' => $canSeeMoney,
        ]);
    }

    public function customer(Request $request, User $user): View
    {
        $this->assertCustomer($user);
        $canSeeMoney = $this->canSeeMoney($request);
        $orders = $user->orders();

        return view('admin.crm.customer', [
            'customer' => $user->load(['organizations:id,name,type,status', 'crmTags']),
            'stats' => [
                'orders' => (clone $orders)->count(),
                'active_orders' => (clone $orders)->where('status', '!=', OrderStatus::Cancelled->value)->count(),
                'spent' => $canSeeMoney ? (int) (clone $orders)->where('status', '!=', OrderStatus::Cancelled->value)->sum('total_paisa') : null,
                'first' => (clone $orders)->min('placed_at'),
                'last' => (clone $orders)->max('placed_at'),
            ],
            'recentOrders' => (clone $orders)->latest('id')->limit(10)->get(['id', 'number', 'status', 'payment_status', 'total_paisa', 'placed_at', 'organization_id']),
            'leads' => CrmLead::query()->where('user_id', $user->id)->latest('id')->limit(10)->get(),
            'timeline' => $user->crmActivities()->with('actor:id,name')->limit(100)->get(),
            'tasks' => $user->crmTasks()->with('assignee:id,name')->get(),
            'crmUsers' => $this->crm->users(),
            'canSeeMoney' => $canSeeMoney,
        ]);
    }

    public function organization(Request $request, Organization $organization): View
    {
        $canSeeMoney = $this->canSeeMoney($request);
        $orders = $organization->orders();

        return view('admin.crm.organization', [
            'organization' => $organization->load(['members:id,name,email,phone', 'crmTags']),
            'stats' => [
                'orders' => (clone $orders)->count(),
                'spent' => $canSeeMoney ? (int) (clone $orders)->where('status', '!=', OrderStatus::Cancelled->value)->sum('total_paisa') : null,
                'last' => (clone $orders)->max('placed_at'),
                'open_quotations' => $organization->quotations()->whereIn('status', ['requested', 'quoted', 'accepted'])->count(),
            ],
            'recentOrders' => (clone $orders)->limit(10)->get(['id', 'number', 'status', 'payment_status', 'total_paisa', 'placed_at']),
            'leads' => CrmLead::query()->where('organization_id', $organization->id)->latest('id')->limit(10)->get(),
            'timeline' => $organization->crmActivities()->with('actor:id,name')->limit(100)->get(),
            'tasks' => $organization->crmTasks()->with('assignee:id,name')->get(),
            'crmUsers' => $this->crm->users(),
            'canSeeMoney' => $canSeeMoney,
        ]);
    }

    public function tasks(Request $request): View
    {
        $scope = $request->query('scope') === 'all' ? 'all' : 'mine';

        return view('admin.crm.tasks', [
            'tasks' => CrmTask::query()->whereNull('completed_at')
                ->when($scope === 'mine', fn ($query) => $query->where('assigned_to', $request->user()->id))
                ->with(['subject', 'assignee:id,name'])->orderBy('due_at')->paginate(50)->withQueryString(),
            'scope' => $scope,
        ]);
    }

    public function logActivity(Request $request, string $type, int $id): RedirectResponse
    {
        $subject = $this->subject($type, $id);
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_map(fn ($case) => $case->value, ActivityType::loggable()))],
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'occurred_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
        ]);

        $occurredAt = filled($validated['occurred_at'] ?? null) ? Carbon::createFromFormat('Y-m-d\TH:i', $validated['occurred_at'], 'Asia/Dhaka')->utc() : null;
        if ($occurredAt?->isFuture()) {
            $occurredAt = now();
        }
        $this->crm->logActivity($subject, ActivityType::from($validated['type']), $validated['body'], $request->user(), $occurredAt);

        return back()->with('status', 'Added to the timeline.');
    }

    public function addTask(Request $request, string $type, int $id): RedirectResponse
    {
        $subject = $this->subject($type, $id);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'due_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->crm->addTask(
            $subject,
            $validated['title'],
            Carbon::createFromFormat('Y-m-d\TH:i', $validated['due_at'], 'Asia/Dhaka')->utc(),
            filled($validated['assigned_to'] ?? null) ? User::query()->find($validated['assigned_to']) : null,
            $request->user(),
        );

        return back()->with('status', 'Follow-up task added.');
    }

    public function completeTask(Request $request, CrmTask $task): RedirectResponse
    {
        $this->crm->completeTask($task, $request->user());

        return back()->with('status', 'Task completed.');
    }

    public function tag(Request $request, string $type, int $id): RedirectResponse
    {
        $subject = $this->subject($type, $id);
        $validated = $request->validate(['tags' => ['nullable', 'string', 'max:400']]);

        $this->crm->tag($subject, explode(',', (string) ($validated['tags'] ?? '')), $request->user());

        return back()->with('status', 'Tags saved.');
    }

    /**
     * CRM records attach to leads, customers (never staff accounts) and organizations only.
     */
    private function subject(string $type, int $id): Model
    {
        return match ($type) {
            'lead' => CrmLead::query()->findOrFail($id),
            'customer' => tap(User::query()->findOrFail($id), fn (User $user) => $this->assertCustomer($user)),
            'organization' => Organization::query()->findOrFail($id),
            default => abort(404),
        };
    }

    private function assertCustomer(User $user): void
    {
        abort_if($user->roles()->exists(), 404);
    }

    private function canSeeMoney(Request $request): bool
    {
        return $request->user()->can(StaffPermission::ViewFinancialReports->value) || $request->user()->can(StaffPermission::ManageOrders->value);
    }

    /**
     * @return array{type: string, tag: string, min_orders: ?int, min_spent: ?int, inactive_days: ?int, district: string, q: string}
     */
    private function filters(Request $request): array
    {
        $int = fn (string $key) => is_numeric($request->query($key)) && (int) $request->query($key) >= 0 ? (int) $request->query($key) : null;

        return [
            'type' => in_array($request->query('type'), ['retail', 'business', 'reseller'], true) ? (string) $request->query('type') : '',
            'tag' => trim((string) $request->query('tag', '')),
            'min_orders' => $int('min_orders'),
            'min_spent' => $this->canSeeMoney($request) && filled($request->query('min_spent')) ? Taka::parse((string) $request->query('min_spent')) : null,
            'inactive_days' => $int('inactive_days'),
            'district' => trim((string) $request->query('district', '')),
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    /**
     * Customers (accounts without staff roles) with order aggregates over non-cancelled orders.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<User>
     */
    private function segmentQuery(array $filters, bool $canSeeMoney): Builder
    {
        $active = fn ($orders) => $orders->where('status', '!=', OrderStatus::Cancelled->value);

        return User::query()
            ->whereDoesntHave('roles')
            ->withCount(['orders' => $active])
            ->withSum(['orders' => $active], 'total_paisa')
            ->withMax(['orders' => $active], 'placed_at')
            ->with(['organizations:id,name,type,status', 'crmTags'])
            ->when($filters['type'] === 'retail', fn ($query) => $query->whereDoesntHave('organizations', fn ($orgs) => $orgs->where('status', OrganizationStatus::Approved->value)))
            ->when(in_array($filters['type'], ['business', 'reseller'], true), fn ($query) => $query->whereHas('organizations', fn ($orgs) => $orgs
                ->where('status', OrganizationStatus::Approved->value)->where('type', OrganizationType::from($filters['type'])->value)))
            ->when($filters['tag'] !== '', fn ($query) => $query->whereHas('crmTags', fn ($tags) => $tags->where('name', mb_strtolower($filters['tag']))))
            ->when($filters['min_orders'] !== null && $filters['min_orders'] > 0, fn ($query) => $query->whereHas('orders', $active, '>=', $filters['min_orders']))
            ->when($canSeeMoney && $filters['min_spent'] !== null, fn ($query) => $query->whereRaw(
                '(select coalesce(sum(o.total_paisa), 0) from orders o where o.user_id = users.id and o.status <> ?) >= ?',
                [OrderStatus::Cancelled->value, $filters['min_spent']],
            ))
            ->when($filters['inactive_days'] !== null, fn ($query) => $query->whereHas('orders', $active)
                ->whereDoesntHave('orders', fn ($orders) => $active($orders)->where('placed_at', '>=', now()->subDays($filters['inactive_days']))))
            ->when($filters['district'] !== '', fn ($query) => $query->whereHas('orders', fn ($orders) => $orders->where('shipping_address->district', $filters['district'])))
            ->when($filters['q'] !== '', fn ($query) => $query->where(fn ($match) => $match
                ->where('name', 'like', '%'.addcslashes($filters['q'], '%_\\').'%')
                ->orWhere('email', 'like', '%'.addcslashes($filters['q'], '%_\\').'%')
                ->orWhere('phone', 'like', '%'.addcslashes($filters['q'], '%_\\').'%')))
            ->orderByDesc('orders_count')->orderBy('name');
    }
}
