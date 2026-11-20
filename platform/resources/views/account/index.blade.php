@extends('layouts.app', ['title' => __('My account')])

@section('content')
    <div class="role-head role-retail reveal">
        <x-icon name="user" class="icon icon-lg" />
        <div>
            <h1>{{ __('Hello, :name', ['name' => $user->name]) }}</h1>
            <p>{{ __('Customer dashboard') }} · {{ $user->phone ?? $user->email }}</p>
        </div>
    </div>

    <div class="portal-shortcuts reveal reveal-2" style="margin-bottom:16px">
        <a class="card card-pad" href="{{ route('account.profile') }}"><x-icon name="user" /><strong>{{ __('Profile & password') }}</strong><span class="muted small">{{ __('Name, mobile, email and password.') }}</span></a>
        <a class="card card-pad" href="#orders"><x-icon name="list" /><strong>{{ __('My orders') }}</strong><span class="muted small">{{ __('Status, payment and invoices.') }}</span></a>
        <a class="card card-pad" href="{{ route('contact') }}"><x-icon name="info" /><strong>{{ __('Contact & support') }}</strong><span class="muted small">{{ __('Questions about an order or product.') }}</span></a>
        @can('admin.access')
            <a class="card card-pad" href="{{ route('account.security') }}"><x-icon name="lock" /><strong>{{ __('Security & MFA') }}</strong><span class="muted small">{{ __('Two-step sign-in for staff.') }}</span></a>
        @endcan
    </div>

    <div class="card reveal reveal-2" style="margin-bottom:16px">
        <div class="panel-title"><h2>{{ __('Business & reseller accounts') }}</h2></div>
        @if ($memberships->isEmpty())
            <div class="card-pad">
                <p class="muted">{{ __('Buying for a restaurant, café, bakery or shop? Apply for protected business or reseller pricing.') }}</p>
                <div class="row">
                    <a class="btn btn-teal" href="{{ route('organizations.apply', 'business') }}">{{ __('Apply for a business account') }}</a>
                    <a class="btn btn-navy" href="{{ route('organizations.apply', 'reseller') }}">{{ __('Apply as reseller') }}</a>
                </div>
            </div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>{{ __('Organization') }}</th><th>{{ __('Type') }}</th><th>{{ __('Your role') }}</th><th>{{ __('Status') }}</th><th><span class="sr-only">{{ __('Actions') }}</span></th></tr></thead>
                    <tbody>
                    @foreach ($memberships as $organization)
                        <tr>
                            <td><strong>{{ $organization->name }}</strong></td>
                            <td>{{ $organization->type === \App\Enums\OrganizationType::Business ? __('Business account') : __('Reseller account') }}</td>
                            <td>{{ __(\App\Enums\OrganizationRole::from($organization->pivot->role)->label()) }}</td>
                            <td><span class="status {{ $organization->isApproved() ? 'ok' : 'warn' }}">{{ __(ucfirst($organization->status->value)) }}</span></td>
                            <td class="row" style="gap:6px;flex-wrap:nowrap">
                                @if ($organization->isApproved())
                                    <form method="POST" action="{{ route('account.context') }}">
                                        @csrf
                                        <input type="hidden" name="organization_id" value="{{ $organization->id }}">
                                        <button class="btn btn-outline btn-sm" type="submit">{{ __('Buy for this account') }}</button>
                                    </form>
                                    <a class="btn btn-primary btn-sm" href="{{ route($organization->type->value.'.dashboard') }}">{{ __('Open dashboard') }}</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="card reveal reveal-3" id="orders">
        <div class="panel-title"><h2>{{ __('My orders') }}</h2></div>
        @if ($orders->isEmpty())
            <div class="empty-state"><x-icon name="cart" /><p><strong>{{ __('No orders yet') }}</strong></p><a class="btn btn-primary" href="{{ route('catalog.shop') }}">{{ __('Start shopping') }}</a></div>
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('Order') }}</th><th>{{ __('Placed') }}</th><th>{{ __('Total') }}</th><th>{{ __('Payment') }}</th><th>{{ __('Status') }}</th><th><span class="sr-only">{{ __('Actions') }}</span></th></tr></thead>
                <tbody>@foreach ($orders as $order)
                    <tr><td class="tnum"><strong>{{ $order->number }}</strong></td><td class="small">{{ $order->placed_at->timezone('Asia/Dhaka')->format('d M Y') }}</td>
                        <td class="tnum">{{ \App\Support\Taka::format($order->total_paisa) }}</td>
                        <td><span class="status {{ $order->isPaid() ? 'ok' : 'warn' }}">{{ __($order->payment_status->label()) }}</span></td>
                        <td><span class="status {{ $order->status->tone() }}">{{ __($order->status->label()) }}</span></td>
                        <td><a class="btn btn-outline btn-sm" href="{{ route('orders.show', $order) }}">{{ __('View') }}</a></td></tr>
                @endforeach</tbody></table></div>
        @endif
    </div>
@endsection
