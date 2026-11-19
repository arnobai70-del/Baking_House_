@extends('layouts.app', ['title' => 'Admin'])

@section('content')
    <div class="admin-top">
        <h1>Admin dashboard</h1>
        <span class="spacer"></span>
        @can('audit.view')
            <a class="btn btn-outline btn-sm" href="{{ route('admin.audit') }}">Audit log</a>
        @endcan
    </div>

    <div class="card">
        <div class="panel-title"><h2>Pending business &amp; reseller applications ({{ $pendingOrganizations->count() }})</h2></div>
        @if ($pendingOrganizations->isEmpty())
            <div class="empty-state"><p><strong>Nothing to review</strong></p></div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Organization</th><th>Type</th><th>Applicant</th><th>Applied</th><th>Decision</th></tr></thead>
                    <tbody>
                    @foreach ($pendingOrganizations as $organization)
                        <tr>
                            <td><strong>{{ $organization->name }}</strong><div class="muted small">{{ $organization->business_category }}</div></td>
                            <td>{{ $organization->type->label() }}</td>
                            <td>{{ $organization->members->first()?->name }}<div class="muted small">{{ $organization->contact_phone }}</div></td>
                            <td class="small">{{ $organization->created_at->diffForHumans() }}</td>
                            <td>
                                @can('decide', $organization)
                                    <form method="POST" action="{{ route('admin.organizations.status', $organization) }}" class="row" style="flex-wrap:nowrap">
                                        @csrf
                                        @method('PATCH')
                                        <label class="sr-only" for="reason-{{ $organization->id }}">Reason</label>
                                        <input class="input" id="reason-{{ $organization->id }}" name="reason" placeholder="Reason (required)" required style="min-height:36px">
                                        <button class="btn btn-primary btn-sm" name="status" value="approved">Approve</button>
                                        <button class="btn btn-ghost btn-sm" name="status" value="rejected">Reject</button>
                                    </form>
                                @else
                                    <span class="muted small">No permission</span>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
