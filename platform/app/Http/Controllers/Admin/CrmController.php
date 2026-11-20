<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Http\Controllers\Controller;
use App\Models\CrmLead;
use App\Models\CrmTask;
use App\Models\Organization;
use App\Models\User;
use App\Services\Crm\CrmService;
use App\Support\BangladeshPhone;
use App\Support\Taka;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * CRM home, leads and the sales pipeline (crm.access). Unassigned leads form the shared queue.
 */
class CrmController extends Controller
{
    public function __construct(private CrmService $crm) {}

    public function dashboard(Request $request): View
    {
        $user = $request->user();

        return view('admin.crm.dashboard', [
            'queue' => CrmLead::query()->whereNull('assigned_to')->whereIn('stage', array_map(fn ($stage) => $stage->value, LeadStage::open()))
                ->with('organization:id,name')->oldest()->limit(25)->get(),
            'mine' => CrmLead::query()->where('assigned_to', $user->id)->whereIn('stage', array_map(fn ($stage) => $stage->value, LeadStage::open()))
                ->with('organization:id,name')->orderByRaw('next_follow_up_at is null')->orderBy('next_follow_up_at')->limit(25)->get(),
            'tasks' => CrmTask::query()->where('assigned_to', $user->id)->whereNull('completed_at')->with('subject')->orderBy('due_at')->limit(25)->get(),
            'pipeline' => $this->pipeline(),
        ]);
    }

    public function index(Request $request): View
    {
        $stage = LeadStage::tryFrom((string) $request->query('stage', ''));
        $owner = (string) $request->query('owner', 'all');
        $search = trim((string) $request->query('q', ''));

        return view('admin.crm.leads', [
            'leads' => CrmLead::query()
                ->when($stage, fn ($query) => $query->where('stage', $stage->value))
                ->when($owner === 'queue', fn ($query) => $query->whereNull('assigned_to'))
                ->when($owner === 'mine', fn ($query) => $query->where('assigned_to', $request->user()->id))
                ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match
                    ->where('title', 'like', '%'.addcslashes($search, '%_\\').'%')
                    ->orWhere('contact_name', 'like', '%'.addcslashes($search, '%_\\').'%')
                    ->orWhere('contact_phone', 'like', '%'.addcslashes($search, '%_\\').'%')))
                ->with(['assignee:id,name', 'organization:id,name'])
                ->latest('id')->paginate(30)->withQueryString(),
            'stage' => $stage,
            'owner' => $owner,
            'search' => $search,
            'pipeline' => $this->pipeline(),
            'sources' => LeadSource::manual(),
            'organizations' => Organization::query()->orderBy('name')->limit(500)->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'source' => ['required', Rule::in(array_map(fn ($source) => $source->value, LeadSource::manual()))],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email:rfc', 'max:255'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'estimated_value' => ['nullable', 'string', 'max:20'],
            'assign_to_me' => ['nullable', 'boolean'],
        ]);

        $phone = filled($validated['contact_phone'] ?? null) ? BangladeshPhone::normalize($validated['contact_phone']) : null;
        if (filled($validated['contact_phone'] ?? null) && $phone === null) {
            throw ValidationException::withMessages(['contact_phone' => 'Enter a valid Bangladesh mobile number.']);
        }

        $lead = $this->crm->createLead([
            'title' => $validated['title'],
            'source' => $validated['source'],
            'contact_name' => $validated['contact_name'] ?? null,
            'contact_phone' => $phone,
            'contact_email' => $validated['contact_email'] ?? null,
            'organization_id' => $validated['organization_id'] ?? null,
            'user_id' => null,
            'estimated_value_paisa' => filled($validated['estimated_value'] ?? null) ? Taka::parse($validated['estimated_value']) : null,
        ], $request->user(), $request->boolean('assign_to_me'));

        return redirect()->route('admin.crm.leads.show', $lead)->with('status', 'Lead created.');
    }

    public function show(Request $request, CrmLead $lead): View
    {
        return view('admin.crm.lead', [
            'lead' => $lead->load(['assignee:id,name', 'organization:id,name,type,status', 'customer:id,name,email,phone', 'quotation:id,number,status,total_paisa', 'crmTags']),
            'timeline' => $lead->crmActivities()->with('actor:id,name')->limit(100)->get(),
            'tasks' => $lead->crmTasks()->with('assignee:id,name')->get(),
            'crmUsers' => $this->crm->users(),
        ]);
    }

    public function assign(Request $request, CrmLead $lead): RedirectResponse
    {
        $validated = $request->validate(['assigned_to' => ['nullable', 'string', 'max:20']]);
        $target = match ($validated['assigned_to'] ?? '') {
            '', 'queue' => null,
            'me' => $request->user(),
            default => User::query()->findOrFail((int) $validated['assigned_to']),
        };

        $this->crm->assign($lead, $target, $request->user());

        return back()->with('status', $target === null ? 'Lead returned to the shared queue.' : 'Lead assigned to '.$target->name.'.');
    }

    public function stage(Request $request, CrmLead $lead): RedirectResponse
    {
        $validated = $request->validate(['stage' => ['required', Rule::enum(LeadStage::class)], 'reason' => ['nullable', 'string', 'max:500']]);

        $this->crm->moveStage($lead, LeadStage::from($validated['stage']), $request->user(), $validated['reason'] ?? null);

        return back()->with('status', 'Stage updated.');
    }

    /**
     * @return array<string, array{count: int, value: int}>
     */
    private function pipeline(): array
    {
        $rows = CrmLead::query()->selectRaw('stage, count(*) as leads, coalesce(sum(estimated_value_paisa), 0) as value')->groupBy('stage')->get()->keyBy('stage');

        return collect(LeadStage::cases())->mapWithKeys(fn (LeadStage $stage) => [$stage->value => [
            'count' => (int) ($rows[$stage->value]->leads ?? 0),
            'value' => (int) ($rows[$stage->value]->value ?? 0),
        ]])->all();
    }
}
