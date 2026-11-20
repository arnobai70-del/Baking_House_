@extends('layouts.admin', ['title' => $organization->name])

@use('App\Support\Taka')

@section('admin')
    <div class="admin-top">
        <h1>{{ $organization->name }}</h1>
        <span class="status {{ $organization->isApproved() ? 'ok' : 'warn' }}">{{ ucfirst($organization->status->value) }}</span>
        <span class="spacer"></span>
        <a class="btn btn-ghost btn-sm" href="{{ route('admin.organizations.index') }}">← All organizations</a>
    </div>

    <div class="admin-grid">
        <section>
            <div class="card card-pad" style="margin-bottom:16px">
                <table class="specs small"><tbody>
                    <tr><th>Type</th><td>{{ $organization->type->label() }}</td></tr>
                    <tr><th>Category</th><td>{{ $organization->business_category ?? '—' }}</td></tr>
                    <tr><th>Trade licence</th><td>{{ $organization->trade_licence_no ?? '—' }}</td></tr>
                    <tr><th>Contact</th><td>{{ $organization->contact_phone ?? '—' }}</td></tr>
                    <tr><th>Approved</th><td>{{ $organization->approved_at?->timezone('Asia/Dhaka')->format('d M Y') ?? '—' }}</td></tr>
                </tbody></table>
            </div>

            <div class="card" style="margin-bottom:16px">
                <div class="panel-title"><h2>Members ({{ $organization->members->count() }})</h2></div>
                <div class="table-wrap"><table class="table"><tbody>
                    @foreach ($organization->members as $member)
                        <tr><td>{{ $member->name }}<div class="muted small">{{ $member->email }} · {{ $member->phone }}</div></td><td>{{ \App\Enums\OrganizationRole::from($member->pivot->role)->label() }}</td></tr>
                    @endforeach
                </tbody></table></div>
            </div>

            <div class="card" style="margin-bottom:16px">
                <div class="panel-title"><h2>Invitations</h2></div>
                <div class="table-wrap"><table class="table"><tbody>
                    @forelse ($invitations as $invitation)
                        <tr><td>{{ $invitation->email }}</td><td>{{ $invitation->role->label() }}</td>
                            <td class="small">{{ $invitation->accepted_at ? 'Accepted' : ($invitation->revoked_at ? 'Cancelled' : ($invitation->isPending() ? 'Pending' : 'Expired')) }}</td></tr>
                    @empty
                        <tr><td class="muted">None.</td></tr>
                    @endforelse
                </tbody></table></div>
            </div>

            <div class="card">
                <div class="panel-title"><h2>Saved delivery addresses</h2></div>
                <div class="card-pad small">
                    @forelse ($organization->addresses as $address)
                        <p style="margin:0 0 6px"><strong>{{ $address->label }}</strong>{{ $address->is_default ? ' (default)' : '' }} — {{ $address->line }}, {{ $address->area }}, {{ $address->district }}</p>
                    @empty
                        <p class="muted" style="margin:0">None.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section>
            <div class="card" style="margin-bottom:16px">
                <div class="panel-title"><h2>Contract price lists</h2>@can('prices.manage')<a class="btn btn-outline btn-sm" href="{{ route('admin.price-lists.create') }}">New price list</a>@endcan</div>
                <div class="table-wrap"><table class="table"><tbody>
                    @forelse ($organization->contractPriceLists as $list)
                        <tr><td>@can('prices.manage')<a href="{{ route('admin.price-lists.edit', $list) }}">{{ $list->name }}</a>@else{{ $list->name }}@endcan</td>
                            <td class="small">{{ $list->items_count }} tiers</td><td><span class="status {{ $list->is_active ? 'ok' : '' }}">{{ $list->is_active ? 'Active' : 'Inactive' }}</span></td></tr>
                    @empty
                        <tr><td class="muted">No contract prices. The organization gets the {{ $organization->type->value }} tier price lists.</td></tr>
                    @endforelse
                </tbody></table></div>
            </div>

            <div class="card" style="margin-bottom:16px">
                <div class="panel-title"><h2>Recent orders</h2></div>
                <div class="table-wrap"><table class="table"><tbody>
                    @forelse ($orders as $order)
                        <tr><td class="tnum">@can('orders.manage')<a href="{{ route('admin.orders.show', $order) }}">{{ $order->number }}</a>@else{{ $order->number }}@endcan</td>
                            <td class="tnum">{{ Taka::format($order->total_paisa) }}</td><td><span class="status {{ $order->status->tone() }}">{{ $order->status->label() }}</span></td></tr>
                    @empty
                        <tr><td class="muted">No orders.</td></tr>
                    @endforelse
                </tbody></table></div>
            </div>

            <div class="card">
                <div class="panel-title"><h2>Quotations</h2></div>
                <div class="table-wrap"><table class="table"><tbody>
                    @forelse ($quotations as $quotation)
                        <tr><td class="tnum">@can('quotations.manage')<a href="{{ route('admin.quotations.show', $quotation) }}">{{ $quotation->number }}</a>@else{{ $quotation->number }}@endcan</td>
                            <td class="tnum">{{ $quotation->total_paisa !== null ? Taka::format($quotation->total_paisa) : '—' }}</td><td><span class="status {{ $quotation->status->tone() }}">{{ $quotation->status->label() }}</span></td></tr>
                    @empty
                        <tr><td class="muted">No quotations.</td></tr>
                    @endforelse
                </tbody></table></div>
            </div>
        </section>
    </div>
@endsection
