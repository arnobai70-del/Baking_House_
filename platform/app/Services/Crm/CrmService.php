<?php

namespace App\Services\Crm;

use App\Enums\ActivityType;
use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Enums\OrganizationStatus;
use App\Enums\StaffPermission;
use App\Models\CrmActivity;
use App\Models\CrmLead;
use App\Models\CrmTag;
use App\Models\CrmTask;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Quotation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\OrderNotifier;
use App\Support\Taka;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The CRM: leads / opportunities with a sales pipeline, communication history, follow-up tasks
 * and tags. Every change is written to the append-only CRM timeline and the audit log.
 *
 * Every RFQ automatically becomes an UNASSIGNED lead in the shared queue; quotation events move
 * it through the pipeline (issued → quoted, ordered → won, declined / closed / expired → lost).
 */
class CrmService
{
    public function __construct(private AuditLogger $audit, private OrderNotifier $notifier) {}

    public function captureRfq(Quotation $quotation): CrmLead
    {
        $lead = new CrmLead;
        $lead->forceFill([
            'title' => $quotation->number.': '.mb_strimwidth(trim(strtok($quotation->request_text, "\n") ?: ''), 0, 120, '…'),
            'source' => LeadSource::Rfq,
            'stage' => LeadStage::New,
            'contact_name' => $quotation->contact_name,
            'contact_phone' => $quotation->contact_phone,
            'contact_email' => $quotation->contact_email,
            'organization_id' => $quotation->organization_id,
            'user_id' => $quotation->requested_by,
            'quotation_id' => $quotation->id,
            'assigned_to' => null,
        ])->save();

        $this->system($lead, 'Quotation request '.$quotation->number.' received. Waiting in the shared queue.');

        return $lead;
    }

    public function captureApplication(Organization $organization, User $applicant): CrmLead
    {
        $lead = new CrmLead;
        $lead->forceFill([
            'title' => $organization->type->label().' application: '.$organization->name,
            'source' => LeadSource::Application,
            'stage' => LeadStage::New,
            'contact_name' => $applicant->name,
            'contact_phone' => $organization->contact_phone ?? $applicant->phone,
            'contact_email' => $applicant->email,
            'organization_id' => $organization->id,
            'user_id' => $applicant->id,
        ])->save();

        $this->system($lead, 'Account application received.');

        return $lead;
    }

    /**
     * Keep the RFQ lead's stage in step with its quotation.
     */
    public function quotationChanged(Quotation $quotation, string $event): void
    {
        $lead = CrmLead::query()->where('quotation_id', $quotation->id)->first();
        if ($lead === null) {
            return;
        }

        [$stage, $message] = match ($event) {
            'issued' => [LeadStage::Quoted, 'Quote issued for '.$this->money($quotation->total_paisa).'.'],
            'accepted' => [null, 'Customer accepted the quote.'],
            'ordered' => [LeadStage::Won, 'Quote ordered'.($quotation->order_id ? ' as order '.Order::query()->whereKey($quotation->order_id)->value('number') : '').'.'],
            'reopened' => [LeadStage::Quoted, 'Order cancelled; quote is open again.'],
            'declined' => [LeadStage::Lost, 'Customer declined the quote.'],
            'closed' => [LeadStage::Lost, 'Quotation closed by staff.'],
            'expired' => [LeadStage::Lost, 'Quote expired.'],
            default => [null, null],
        };
        if ($message === null) {
            return;
        }

        if ($stage !== null && $stage !== $lead->stage) {
            $lead->forceFill([
                'stage' => $stage,
                'estimated_value_paisa' => $quotation->total_paisa ?? $lead->estimated_value_paisa,
                'closed_at' => $stage->isClosed() ? now() : null,
                'lost_reason' => $stage === LeadStage::Lost ? $message : null,
            ])->save();
        }
        $this->system($lead, $message);
    }

    /**
     * @param  array{title: string, source: string, contact_name: ?string, contact_phone: ?string, contact_email: ?string, organization_id: ?int, user_id: ?int, estimated_value_paisa: ?int}  $data
     */
    public function createLead(array $data, User $actor, bool $assignToMe): CrmLead
    {
        $lead = new CrmLead;
        $lead->forceFill($data + ['stage' => LeadStage::New, 'assigned_to' => $assignToMe ? $actor->id : null])->save();

        $this->system($lead, 'Lead created by '.$actor->name.'.', $actor);
        $this->audit->record('crm.lead_created', $lead, after: ['title' => $lead->title, 'source' => $lead->source->value, 'assigned_to' => $lead->assigned_to], actorId: $actor->id);

        return $lead;
    }

    /**
     * Assign to a CRM user, or back to the shared queue (null). Conditional on the current owner so
     * two people claiming the same queued lead cannot both win.
     *
     * @throws ValidationException
     */
    public function assign(CrmLead $lead, ?User $assignee, User $actor): void
    {
        if ($assignee !== null && (! $assignee->is_active || ! $assignee->can(StaffPermission::AccessCrm->value))) {
            throw ValidationException::withMessages(['assigned_to' => 'That person does not have CRM access.']);
        }

        $from = $lead->assigned_to;
        $updated = CrmLead::query()->whereKey($lead->id)
            ->when($from === null, fn ($query) => $query->whereNull('assigned_to'), fn ($query) => $query->where('assigned_to', $from))
            ->update(['assigned_to' => $assignee?->id, 'updated_at' => now()]);
        if ($updated !== 1) {
            throw ValidationException::withMessages(['assigned_to' => 'Someone else just took this lead. Reload the page.']);
        }
        $lead->refresh();

        $this->system($lead, $assignee === null ? 'Returned to the shared queue by '.$actor->name.'.' : 'Assigned to '.$assignee->name.' by '.$actor->name.'.', $actor);
        $this->audit->record('crm.lead_assigned', $lead, ['assigned_to' => $from], ['assigned_to' => $assignee?->id], actorId: $actor->id);
    }

    /**
     * @throws ValidationException
     */
    public function moveStage(CrmLead $lead, LeadStage $stage, User $actor, ?string $reason = null): void
    {
        if ($stage === $lead->stage) {
            return;
        }
        if ($stage === LeadStage::Lost && mb_strlen(trim((string) $reason)) < 3) {
            throw ValidationException::withMessages(['reason' => 'Say why the lead was lost.']);
        }

        $from = $lead->stage;
        $lead->forceFill([
            'stage' => $stage,
            'closed_at' => $stage->isClosed() ? now() : null,
            'lost_reason' => $stage === LeadStage::Lost ? trim((string) $reason) : null,
        ])->save();

        $this->system($lead, 'Stage changed from '.$from->label().' to '.$stage->label().'.'.($reason ? ' '.$reason : ''), $actor);
        $this->audit->record('crm.lead_stage_changed', $lead, ['stage' => $from->value], ['stage' => $stage->value], $reason, $actor->id);
    }

    /**
     * @throws ValidationException
     */
    public function logActivity(Model $subject, ActivityType $type, string $body, User $actor, ?Carbon $occurredAt = null): CrmActivity
    {
        if ($type === ActivityType::System) {
            throw ValidationException::withMessages(['type' => 'System entries are written by the application.']);
        }

        $activity = $this->write($subject, $type, $body, $actor, $occurredAt);
        $this->audit->record('crm.activity_logged', $subject, after: ['type' => $type->value, 'activity_id' => $activity->id], actorId: $actor->id);

        return $activity;
    }

    /**
     * @throws ValidationException
     */
    public function addTask(Model $subject, string $title, Carbon $dueAt, ?User $assignee, User $actor): CrmTask
    {
        if ($assignee !== null && ! $assignee->can(StaffPermission::AccessCrm->value)) {
            throw ValidationException::withMessages(['assigned_to' => 'That person does not have CRM access.']);
        }

        $task = new CrmTask;
        $task->forceFill([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'title' => $title,
            'due_at' => $dueAt,
            'assigned_to' => $assignee?->id ?? $actor->id,
            'created_by' => $actor->id,
        ])->save();

        $this->syncFollowUp($subject);
        $this->write($subject, ActivityType::System, 'Follow-up task: "'.$title.'" due '.$dueAt->timezone('Asia/Dhaka')->format('d M Y H:i').'.', $actor);

        return $task;
    }

    public function completeTask(CrmTask $task, User $actor): void
    {
        if ($task->completed_at !== null) {
            return;
        }

        $task->forceFill(['completed_at' => now(), 'completed_by' => $actor->id])->save();
        $subject = $task->subject;
        if ($subject !== null) {
            $this->syncFollowUp($subject);
            $this->write($subject, ActivityType::System, 'Task done: "'.$task->title.'".', $actor);
        }
    }

    /**
     * Replace the tags of a customer, organization or lead (names are normalised, max 10).
     *
     * @param  list<string>  $names
     */
    public function tag(Model $subject, array $names, User $actor): void
    {
        $names = collect($names)->map(fn (string $name) => mb_strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? '')))
            ->filter(fn (string $name) => $name !== '' && mb_strlen($name) <= 40)->unique()->take(10)->values();

        $ids = $names->map(fn (string $name) => CrmTag::query()->firstOrCreate(['name' => $name])->id)->all();
        $subject->crmTags()->sync($ids);

        $this->audit->record('crm.tags_updated', $subject, after: ['tags' => $names->all()], actorId: $actor->id);
    }

    /**
     * Log reminders for open tasks that are due (log / test driver until providers are configured).
     */
    public function sendDueReminders(): int
    {
        $count = 0;
        CrmTask::query()->whereNull('completed_at')->whereNull('reminded_at')->where('due_at', '<=', now())
            ->with('assignee:id,name,email')->orderBy('id')->each(function (CrmTask $task) use (&$count): void {
                $claimed = CrmTask::query()->whereKey($task->id)->whereNull('reminded_at')->update(['reminded_at' => now()]);
                if ($claimed === 1 && $task->assignee !== null) {
                    $this->notifier->direct('crm_task_due', null, $task->assignee->email, 'Baking House CRM: follow-up due',
                        'Follow-up due: '.$task->title.' ('.$task->due_at->timezone('Asia/Dhaka')->format('d M Y H:i').').');
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Active staff who hold crm.access (assignable for leads and tasks).
     *
     * @return Collection<int, User>
     */
    public function users(): Collection
    {
        $permissionId = Permission::query()->where('name', StaffPermission::AccessCrm->value)->value('id');

        return User::query()->where('is_active', true)
            ->whereHas('roles', fn ($roles) => $roles->whereHas('permissions', fn ($permissions) => $permissions->whereKey($permissionId)))
            ->orderBy('name')->get(['id', 'name']);
    }

    /**
     * "Retail", "Business account", "Reseller account" or both, from approved memberships.
     */
    public static function customerType(User $customer): string
    {
        $approved = $customer->organizations->filter(fn (Organization $organization) => $organization->status === OrganizationStatus::Approved);

        return $approved->isEmpty() ? 'Retail' : $approved->map(fn (Organization $organization) => $organization->type->label())->unique()->implode(' + ');
    }

    private function system(Model $subject, string $body, ?User $actor = null): void
    {
        $this->write($subject, ActivityType::System, $body, $actor);
    }

    private function write(Model $subject, ActivityType $type, string $body, ?User $actor, ?Carbon $occurredAt = null): CrmActivity
    {
        $activity = new CrmActivity;
        $activity->forceFill([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'type' => $type,
            'body' => $body,
            'actor_id' => $actor?->id,
            'occurred_at' => $occurredAt ?? now(),
        ])->save();

        return $activity;
    }

    private function syncFollowUp(Model $subject): void
    {
        if ($subject instanceof CrmLead) {
            $next = CrmTask::query()->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->id)
                ->whereNull('completed_at')->min('due_at');
            DB::table('crm_leads')->where('id', $subject->id)->update(['next_follow_up_at' => $next]);
        }
    }

    private function money(?int $paisa): string
    {
        return $paisa === null ? '—' : Taka::format($paisa);
    }
}
