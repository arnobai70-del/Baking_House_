{{-- Tags, follow-up tasks and the communication timeline for a lead / customer / organization.
     Expects: $type ('lead'|'customer'|'organization'), $id, $tags, $timeline, $tasks, $crmUsers --}}
<div class="card card-pad" style="margin-bottom:16px">
    <form method="POST" action="{{ route('admin.crm.tags.store', [$type, $id]) }}" class="row" style="align-items:flex-end;gap:8px" novalidate>
        @csrf
        <div class="field" style="flex:1;margin:0"><label for="tags-{{ $type }}">Tags (comma separated)</label>
            <input class="input" id="tags-{{ $type }}" name="tags" value="{{ $tags->pluck('name')->implode(', ') }}" placeholder="e.g. bakery, high value, chattogram"></div>
        <button class="btn btn-outline btn-sm" type="submit">Save tags</button>
    </form>
</div>

<div class="card" style="margin-bottom:16px">
    <div class="panel-title"><h2>Follow-up tasks</h2></div>
    @if ($tasks->isNotEmpty())
        <div class="table-wrap"><table class="table"><tbody>
            @foreach ($tasks as $task)
                <tr @if ($task->completed_at) class="muted" @endif>
                    <td>@if ($task->completed_at)<s>{{ $task->title }}</s>@else<strong>{{ $task->title }}</strong>@endif<div class="muted small">{{ $task->assignee?->name ?? 'Unassigned' }}</div></td>
                    <td class="small">@if ($task->isOverdue())<span class="status warn">Overdue</span> @endif{{ $task->due_at->timezone('Asia/Dhaka')->format('d M Y H:i') }}</td>
                    <td>@unless ($task->completed_at)<form method="POST" action="{{ route('admin.crm.tasks.complete', $task) }}">@csrf<button class="btn btn-ghost btn-sm" type="submit"><x-icon name="check" /> Done</button></form>@endunless</td>
                </tr>
            @endforeach
        </tbody></table></div>
    @endif
    <form method="POST" action="{{ route('admin.crm.tasks.store', [$type, $id]) }}" class="card-pad row" style="align-items:flex-end;gap:8px" novalidate>
        @csrf
        <div class="field" style="flex:2;margin:0;min-width:180px"><label for="task-title-{{ $type }}">New task</label><input class="input" id="task-title-{{ $type }}" name="title" placeholder="e.g. Call back about oven delivery date" required></div>
        <div class="field" style="margin:0"><label for="task-due-{{ $type }}">Due (Dhaka)</label><input class="input" id="task-due-{{ $type }}" name="due_at" type="datetime-local" value="{{ now('Asia/Dhaka')->addDay()->setTime(10, 0)->format('Y-m-d\TH:i') }}" required></div>
        <div class="field" style="margin:0"><label for="task-owner-{{ $type }}">For</label>
            <select class="input" id="task-owner-{{ $type }}" name="assigned_to"><option value="">Me</option>@foreach ($crmUsers as $crmUser)<option value="{{ $crmUser->id }}">{{ $crmUser->name }}</option>@endforeach</select></div>
        <button class="btn btn-teal btn-sm" type="submit">Add task</button>
    </form>
</div>

<div class="card">
    <div class="panel-title"><h2>Timeline &amp; communication</h2></div>
    <form method="POST" action="{{ route('admin.crm.activities.store', [$type, $id]) }}" class="card-pad" novalidate>
        @csrf
        <div class="row" style="gap:8px;align-items:flex-end">
            <div class="field" style="margin:0"><label for="act-type-{{ $type }}">Type</label>
                <select class="input" id="act-type-{{ $type }}" name="type">@foreach (\App\Enums\ActivityType::loggable() as $activityType)<option value="{{ $activityType->value }}">{{ $activityType->label() }}</option>@endforeach</select></div>
            <div class="field" style="margin:0"><label for="act-when-{{ $type }}">When (optional)</label><input class="input" id="act-when-{{ $type }}" name="occurred_at" type="datetime-local"></div>
        </div>
        <div class="field" style="margin-top:8px"><label for="act-body-{{ $type }}">Note / summary</label><textarea class="input" id="act-body-{{ $type }}" name="body" rows="2" required placeholder="What was discussed or agreed"></textarea></div>
        <button class="btn btn-navy btn-sm" type="submit">Add to timeline</button>
    </form>
    <ol class="crm-timeline">
        @forelse ($timeline as $activity)
            <li class="crm-{{ $activity->type->value }}">
                <div class="small"><strong>{{ $activity->type->label() }}</strong> <span class="muted">· {{ $activity->occurred_at->timezone('Asia/Dhaka')->format('d M Y H:i') }}{{ $activity->actor ? ' · '.$activity->actor->name : '' }}</span></div>
                <div class="small" style="white-space:pre-line">{{ $activity->body }}</div>
            </li>
        @empty
            <li class="muted small">Nothing yet.</li>
        @endforelse
    </ol>
</div>
