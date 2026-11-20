@extends('layouts.admin', ['title' => 'Staff & roles'])

@section('admin')
    <div class="admin-top"><h1>Staff &amp; roles</h1></div>
    <div class="alert alert-info" style="margin-bottom:16px"><x-icon name="lock" /><span>Every change needs a reason and is written to the audit log. Nobody can change their own roles. Financial reports are limited to the super admin and the <strong>Finance</strong> role; give it explicitly where needed.</span></div>

    <div class="card" style="margin-bottom:16px">
        <div class="panel-title"><h2>Staff ({{ $staff->count() }})</h2></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Person</th><th>MFA</th><th>Roles</th></tr></thead>
            <tbody>
            @foreach ($staff as $member)
                <tr>
                    <td><strong>{{ $member->name }}</strong>@unless ($member->is_active) <span class="status">Deactivated</span>@endunless<div class="muted small">{{ $member->email }} · {{ $member->phone }}</div></td>
                    <td><span class="status {{ $member->mfa_confirmed_at ? 'ok' : 'warn' }}">{{ $member->mfa_confirmed_at ? 'Enrolled' : 'Not enrolled' }}</span></td>
                    <td>
                        @if ($member->is(auth()->user()))
                            {{ $member->roles->pluck('label')->implode(', ') }} <span class="muted small">(your own roles cannot be changed here)</span>
                        @else
                            @include('admin._role-form', ['person' => $member])
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>

    <form method="GET" class="card card-pad row" style="margin-bottom:16px;align-items:flex-end">
        <div class="field" style="flex:1;margin:0"><label for="q">Give a role to an existing account — exact email or mobile</label><input class="input" id="q" name="q" value="{{ $search }}"></div>
        <button class="btn btn-outline" type="submit">Find</button>
    </form>
    @if ($search !== '')
        <div class="card" style="margin-bottom:16px"><div class="table-wrap"><table class="table"><tbody>
            @forelse ($found as $person)
                <tr><td><strong>{{ $person->name }}</strong><div class="muted small">{{ $person->email }} · {{ $person->phone }}</div></td><td>@include('admin._role-form', ['person' => $person])</td></tr>
            @empty
                <tr><td class="muted">No customer account matches exactly. The person must register first.</td></tr>
            @endforelse
        </tbody></table></div></div>
    @endif

    <div class="card">
        <div class="panel-title"><h2>Role permissions</h2></div>
        <div class="table-wrap"><table class="table perm-matrix">
            <thead><tr><th>Permission</th>@foreach ($roles as $role)<th>{{ $role->label }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach ($permissions as $permission)
                <tr><td><code>{{ $permission->name }}</code>@if (in_array($permission->name, $superAdminOnly, true)) <span class="status warn">super admin only</span>@endif</td>
                    @foreach ($roles as $role)
                        <td>@if ($role->permissions->contains('id', $permission->id))<x-icon name="check" /><span class="sr-only">yes</span>@else<span class="muted" aria-hidden="true">—</span><span class="sr-only">no</span>@endif</td>
                    @endforeach</tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
@endsection
