@extends('layouts.app', ['title' => $title])

@section('content')
    <div class="role-head {{ $organization->type === \App\Enums\OrganizationType::Business ? 'role-business' : 'role-reseller' }}">
        <div>
            <h1>{{ $title }}</h1>
            <p>{{ $organization->name }} · your role: {{ ucfirst($memberRole?->value ?? 'member') }}</p>
        </div>
    </div>
    <div class="card">
        <div class="empty-state">
            <p><strong>Portal foundation ready</strong></p>
            <p class="muted small">Price lists, quotes, bulk orders and team management are built in Phase 6. Access here is already scoped to {{ $organization->name }} only.</p>
        </div>
    </div>
@endsection
