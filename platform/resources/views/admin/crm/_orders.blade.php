@use('App\Support\Taka')
<div class="card" style="margin-bottom:16px">
    <div class="panel-title"><h2>Recent orders</h2></div>
    <div class="table-wrap"><table class="table"><tbody>
        @forelse ($recentOrders as $order)
            <tr><td class="tnum">@can('orders.manage')<a href="{{ route('admin.orders.show', $order) }}">{{ $order->number }}</a>@else{{ $order->number }}@endcan</td>
                <td class="small">{{ $order->placed_at->timezone('Asia/Dhaka')->format('d M Y') }}</td>
                @if ($canSeeMoney)<td class="tnum">{{ Taka::format($order->total_paisa) }}</td>@endif
                <td><span class="status {{ $order->status->tone() }}">{{ $order->status->label() }}</span></td></tr>
        @empty
            <tr><td class="muted">No orders.</td></tr>
        @endforelse
    </tbody></table></div>
</div>
@if ($leads->isNotEmpty())
    <div class="card" style="margin-bottom:16px">
        <div class="panel-title"><h2>Leads</h2></div>
        <div class="table-wrap"><table class="table"><tbody>
            @foreach ($leads as $lead)
                <tr><td><a href="{{ route('admin.crm.leads.show', $lead) }}">{{ $lead->title }}</a></td><td><span class="status {{ $lead->stage->tone() }}">{{ $lead->stage->label() }}</span></td></tr>
            @endforeach
        </tbody></table></div>
    </div>
@endif
