@extends('layouts.admin', ['title' => 'Reports · promotions'])

@use('App\Support\Taka')

@section('admin')
    @include('admin.reports._header')
    <div class="card">
        <div class="panel-title"><h2>Coupon &amp; promotion performance</h2></div>
        @if ($promotions === [])
            <div class="empty-state" style="padding:24px"><p class="muted small">No promotions were used on orders in this period.</p></div>
        @else
            <div class="table-wrap"><table class="table"><thead><tr><th>Promotion</th><th>Code</th><th>Uses</th><th>Discount given</th><th>Billed on those orders</th><th>Avg discount / use</th></tr></thead><tbody>
                @foreach ($promotions as $row)
                    <tr><td>{{ $row['name'] }}</td><td>{{ $row['code'] ?? 'automatic' }}</td><td class="tnum">{{ $row['uses'] }}</td>
                        <td class="tnum">{{ Taka::format($row['discount']) }}</td><td class="tnum">{{ Taka::format($row['billed']) }}</td>
                        <td class="tnum">{{ Taka::format(intdiv($row['discount'], max(1, $row['uses']))) }}</td></tr>
                @endforeach
            </tbody></table></div>
        @endif
    </div>
    <p class="hint">From redemptions on non-cancelled orders placed in the period (a cancelled order gives its redemption back).</p>
@endsection
