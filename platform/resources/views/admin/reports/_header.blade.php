{{-- Report title, one filter row (date range) and section tabs. --}}
<div class="admin-top">
    <h1>Reports</h1><span class="spacer"></span>
    @unless ($section === 'inventory')<span class="muted small">{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }} (Dhaka)</span>@endunless
    <a class="btn btn-teal btn-sm" href="{{ request()->fullUrlWithQuery(['format' => 'csv']) }}"><x-icon name="file" /> Download CSV</a>
</div>
<div class="row" style="margin-bottom:12px;gap:6px">
    @foreach (['overview' => 'Sales overview', 'products' => 'Products & categories', 'inventory' => 'Inventory', 'promotions' => 'Coupons & promotions', 'customers' => 'Customers', 'rfq' => 'RFQ & pipeline'] as $key => $label)
        @if ($key !== 'promotions' || $money)
            <a class="fchip" href="{{ route('admin.reports', ['section' => $key] + request()->only(['range', 'from', 'to'])) }}" @if ($section === $key) style="background:var(--bh-navy);color:#fff" aria-current="page" @endif>{{ $label }}</a>
        @endif
    @endforeach
</div>
@unless ($section === 'inventory')
    <form method="GET" class="card card-pad row" style="margin-bottom:16px;align-items:flex-end;gap:8px">
        <div class="field" style="margin:0"><label for="range">Period</label>
            <select class="input" id="range" name="range">
                @foreach (['today' => 'Today', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'month' => 'This month', 'last_month' => 'Last month', 'custom' => 'Custom dates'] as $value => $label)
                    <option value="{{ $value }}" @selected($preset === $value)>{{ $label }}</option>
                @endforeach
            </select></div>
        <div class="field" style="margin:0"><label for="from">From</label><input class="input" id="from" name="from" type="date" value="{{ $from->format('Y-m-d') }}"></div>
        <div class="field" style="margin:0"><label for="to">To</label><input class="input" id="to" name="to" type="date" value="{{ $to->format('Y-m-d') }}"></div>
        <button class="btn btn-outline" type="submit">Apply</button>
        <span class="hint">Dates use the custom range only when Period is "Custom dates". Max 1 year.</span>
    </form>
@endunless
@unless ($money)
    <div class="alert alert-info" style="margin-bottom:16px"><x-icon name="lock" /><span>Money figures are hidden: financial reports are limited to the super admin and staff given the Finance role.</span></div>
@endunless
