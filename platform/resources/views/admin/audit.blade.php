@extends('layouts.admin', ['title' => 'Audit log'])

@section('admin')
    <div class="admin-top"><h1>Audit log</h1></div>
    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Subject</th><th>Before → After</th><th>Reason</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="small tnum">{{ $log->created_at->timezone('Asia/Dhaka')->format('Y-m-d H:i') }}</td>
                        <td>{{ $log->actor?->name ?? 'System' }}</td>
                        <td><code>{{ $log->action }}</code></td>
                        <td class="small">{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td>
                        <td class="small"><code>{{ json_encode($log->before) }}</code> → <code>{{ json_encode($log->after) }}</code></td>
                        <td class="small">{{ $log->reason }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty-state"><p><strong>No audit entries yet</strong></p></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div style="margin-top:12px">{{ $logs->links() }}</div>
@endsection
