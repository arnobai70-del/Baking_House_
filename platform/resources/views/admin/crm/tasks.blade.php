@extends('layouts.admin', ['title' => 'CRM tasks'])

@section('admin')
    <div class="admin-top"><h1>Follow-up tasks</h1></div>
    @include('admin.crm._nav')
    <div class="row" style="margin-bottom:12px;gap:6px">
        <a class="fchip" href="{{ route('admin.crm.tasks.index') }}" @if ($scope === 'mine') style="background:var(--bh-navy);color:#fff" @endif>Mine</a>
        <a class="fchip" href="{{ route('admin.crm.tasks.index', ['scope' => 'all']) }}" @if ($scope === 'all') style="background:var(--bh-navy);color:#fff" @endif>Everyone</a>
    </div>
    <div class="card"><div class="table-wrap"><table class="table">
        <thead><tr><th>Task</th><th>About</th><th>Owner</th><th>Due (Dhaka)</th><th></th></tr></thead>
        <tbody>
        @forelse ($tasks as $task)
            <tr><td><strong>{{ $task->title }}</strong></td>
                <td class="small">@include('admin.crm._subject-link', ['subject' => $task->subject])</td>
                <td class="small">{{ $task->assignee?->name ?? '—' }}</td>
                <td class="small">@if ($task->isOverdue())<span class="status warn">Overdue</span> @endif{{ $task->due_at->timezone('Asia/Dhaka')->format('d M Y H:i') }}</td>
                <td><form method="POST" action="{{ route('admin.crm.tasks.complete', $task) }}">@csrf<button class="btn btn-ghost btn-sm" type="submit"><x-icon name="check" /> Done</button></form></td></tr>
        @empty
            <tr><td colspan="5" class="muted">No open tasks.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
    <div style="margin-top:12px">{{ $tasks->links() }}</div>
@endsection
