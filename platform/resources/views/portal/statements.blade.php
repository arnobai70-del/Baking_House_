@extends('portal.layout', ['heading' => __('Invoices & statements'), 'subheading' => __('Monthly statement of every order placed for :organization', ['organization' => $organization->name])])

@use('App\Support\Taka')

@section('portal')
    <div class="card">
        <div class="panel-title">
            <h2>{{ __('Statement for :month', ['month' => $statement['month']->format('F Y')]) }}</h2>
            <div class="row" style="gap:6px">
                <a class="btn btn-ghost btn-sm" href="{{ route($portal.'.statements', ['month' => $previous]) }}">← {{ __('Previous') }}</a>
                @if ($next)<a class="btn btn-ghost btn-sm" href="{{ route($portal.'.statements', ['month' => $next]) }}">{{ __('Next') }} →</a>@endif
                <a class="btn btn-teal btn-sm" href="{{ route($portal.'.statements', ['month' => $statement['month']->format('Y-m'), 'format' => 'csv']) }}"><x-icon name="file" /> {{ __('Download CSV') }}</a>
            </div>
        </div>
        @if ($statement['rows'] === [])
            <div class="empty-state"><x-icon name="file" /><p><strong>{{ __('No orders this month') }}</strong></p><p class="muted small">{{ __('Invoices and statements appear here after you order.') }}</p></div>
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Order') }}</th><th>{{ __('Status') }}</th><th>{{ __('Charged') }}</th><th>{{ __('Paid') }}</th><th>{{ __('Refunded') }}</th><th>{{ __('Balance due') }}</th><th><span class="sr-only">{{ __('Invoice') }}</span></th></tr></thead>
                <tbody>
                @foreach ($statement['rows'] as $row)
                    <tr @if ($row['cancelled']) class="muted" @endif>
                        <td class="small">{{ $row['order']->placed_at->timezone('Asia/Dhaka')->format('d M') }}</td>
                        <td class="tnum"><a href="{{ route('orders.show', $row['order']) }}">{{ $row['order']->number }}</a></td>
                        <td><span class="status {{ $row['order']->status->tone() }}">{{ __($row['order']->status->label()) }}</span></td>
                        <td class="tnum">{{ Taka::format($row['charged']) }}</td>
                        <td class="tnum">{{ Taka::format($row['paid']) }}</td>
                        <td class="tnum">{{ $row['refunded'] ? Taka::format($row['refunded']) : '—' }}</td>
                        <td class="tnum"><strong>{{ Taka::format($row['balance']) }}</strong></td>
                        <td><a class="btn btn-ghost btn-sm" href="{{ route($portal.'.orders.invoice', $row['order']) }}">{{ __('Invoice') }}</a></td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr>
                    <th colspan="3">{{ __('Total') }}</th>
                    <th class="tnum">{{ Taka::format($statement['totals']['charged']) }}</th>
                    <th class="tnum">{{ Taka::format($statement['totals']['paid']) }}</th>
                    <th class="tnum">{{ Taka::format($statement['totals']['refunded']) }}</th>
                    <th class="tnum">{{ Taka::format($statement['totals']['balance']) }}</th>
                    <th></th>
                </tr></tfoot>
            </table></div>
        @endif
    </div>
    <p class="hint">{{ __('Cancelled orders are listed but not charged. Credit terms are not offered online; balances are due as per each order\'s payment method.') }}</p>
@endsection
