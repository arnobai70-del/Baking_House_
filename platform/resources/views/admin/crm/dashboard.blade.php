@extends('layouts.admin', ['title' => 'CRM'])

@use('App\Support\Taka')

@section('admin')
    <div class="admin-top"><h1>CRM</h1></div>
    @include('admin.crm._nav')

    <div class="admin-grid">
        <section>
            <div class="card" style="margin-bottom:16px">
                <div class="panel-title"><h2>Shared queue — unassigned leads ({{ $queue->count() }})</h2></div>
                @if ($queue->isEmpty())
                    <div class="empty-state" style="padding:24px"><p class="muted small">No unassigned leads. New RFQs land here until someone takes them.</p></div>
                @else
                    <div class="table-wrap"><table class="table"><tbody>
                        @foreach ($queue as $lead)
                            <tr>
                                <td><a href="{{ route('admin.crm.leads.show', $lead) }}"><strong>{{ $lead->title }}</strong></a>
                                    <div class="muted small">{{ $lead->source->label() }} · {{ $lead->organization?->name ?? $lead->contact_name }} · waiting {{ $lead->created_at->diffForHumans(null, true) }}</div></td>
                                <td><form method="POST" action="{{ route('admin.crm.leads.assign', $lead) }}">@csrf<input type="hidden" name="assigned_to" value="me"><button class="btn btn-primary btn-sm" type="submit">Take it</button></form></td>
                            </tr>
                        @endforeach
                    </tbody></table></div>
                @endif
            </div>

            <div class="card">
                <div class="panel-title"><h2>My open leads ({{ $mine->count() }})</h2></div>
                @if ($mine->isEmpty())
                    <div class="empty-state" style="padding:24px"><p class="muted small">No leads assigned to you.</p></div>
                @else
                    <div class="table-wrap"><table class="table"><tbody>
                        @foreach ($mine as $lead)
                            <tr><td><a href="{{ route('admin.crm.leads.show', $lead) }}"><strong>{{ $lead->title }}</strong></a><div class="muted small">{{ $lead->organization?->name ?? $lead->contact_name }}</div></td>
                                <td><span class="status {{ $lead->stage->tone() }}">{{ $lead->stage->label() }}</span></td>
                                <td class="small">{{ $lead->next_follow_up_at ? 'Next: '.$lead->next_follow_up_at->timezone('Asia/Dhaka')->format('d M H:i') : 'No follow-up set' }}</td></tr>
                        @endforeach
                    </tbody></table></div>
                @endif
            </div>
        </section>

        <section>
            @include('admin.crm._pipeline')
            <div class="card">
                <div class="panel-title"><h2>My tasks</h2><a class="btn btn-ghost btn-sm" href="{{ route('admin.crm.tasks.index') }}">All tasks</a></div>
                @if ($tasks->isEmpty())
                    <div class="empty-state" style="padding:24px"><p class="muted small">No open tasks.</p></div>
                @else
                    <div class="table-wrap"><table class="table"><tbody>
                        @foreach ($tasks as $task)
                            <tr><td>{{ $task->title }}<div class="muted small">@include('admin.crm._subject-link', ['subject' => $task->subject])</div></td>
                                <td class="small">@if ($task->isOverdue())<span class="status warn">Overdue</span> @endif{{ $task->due_at->timezone('Asia/Dhaka')->format('d M H:i') }}</td>
                                <td><form method="POST" action="{{ route('admin.crm.tasks.complete', $task) }}">@csrf<button class="btn btn-ghost btn-sm" type="submit">Done</button></form></td></tr>
                        @endforeach
                    </tbody></table></div>
                @endif
            </div>
        </section>
    </div>
@endsection
