@extends('layouts.admin', ['title' => 'Organizations'])

@section('admin')
    <div class="admin-top"><h1>Business &amp; reseller organizations</h1></div>
    <form method="GET" class="card card-pad row" style="margin-bottom:16px;align-items:flex-end">
        <div class="field" style="flex:1;margin:0"><label for="q">Name</label><input class="input" id="q" name="q" value="{{ $search }}"></div>
        <div class="field" style="margin:0"><label for="type">Type</label>
            <select class="input" id="type" name="type"><option value="">Any</option>
                @foreach (\App\Enums\OrganizationType::cases() as $case)<option value="{{ $case->value }}" @selected($type === $case)>{{ $case->label() }}</option>@endforeach</select></div>
        <div class="field" style="margin:0"><label for="status">Status</label>
            <select class="input" id="status" name="status"><option value="">Any</option>
                @foreach (\App\Enums\OrganizationStatus::cases() as $case)<option value="{{ $case->value }}" @selected($status === $case)>{{ ucfirst($case->value) }}</option>@endforeach</select></div>
        <button class="btn btn-outline" type="submit">Filter</button>
    </form>
    <div class="card"><div class="table-wrap"><table class="table">
        <thead><tr><th>Organization</th><th>Type</th><th>Status</th><th>Members</th><th>Orders</th><th></th></tr></thead>
        <tbody>
        @forelse ($organizations as $organization)
            <tr>
                <td><strong>{{ $organization->name }}</strong><div class="muted small">{{ $organization->business_category }}</div></td>
                <td>{{ $organization->type->label() }}</td>
                <td><span class="status {{ $organization->isApproved() ? 'ok' : 'warn' }}">{{ ucfirst($organization->status->value) }}</span></td>
                <td class="tnum">{{ $organization->members_count }}</td>
                <td class="tnum">{{ $organization->orders_count }}</td>
                <td><a class="btn btn-outline btn-sm" href="{{ route('admin.organizations.show', $organization) }}">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No organizations.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
    <div style="margin-top:12px">{{ $organizations->links() }}</div>
@endsection
