@extends('portal.layout', ['heading' => $portal === 'business' ? __('Business pricing') : __('My rate card'), 'subheading' => __('Worked out on our server for :organization only; the same prices apply in your cart.', ['organization' => $organization->name])])

@use('App\Support\Taka')
@use('App\Support\Weight')

@section('portal')
    <div class="card">
        <div class="panel-title">
            <h2>{{ $portal === 'business' ? __('Your price list') : __('Assigned reseller prices') }}</h2>
            <div class="row" style="gap:6px">
                <form method="GET" class="row" style="gap:6px" role="search">
                    <label class="sr-only" for="q">{{ __('Search') }}</label>
                    <input class="input" id="q" name="q" value="{{ $search }}" placeholder="{{ __('SKU or product') }}" style="min-height:36px;width:180px">
                    <button class="btn btn-outline btn-sm" type="submit"><x-icon name="search" /> {{ __('Search') }}</button>
                </form>
                <a class="btn btn-teal btn-sm" href="{{ route($portal.'.prices.export') }}"><x-icon name="file" /> {{ __('Download CSV') }}</a>
            </div>
        </div>
        @if ($rows === [])
            <div class="empty-state"><x-icon name="tag" /><p><strong>{{ __('No special prices yet') }}</strong></p><p class="muted small">{{ __('Our team assigns your price list after approval. Until then you see standard prices.') }}</p></div>
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Public price') }}</th><th>{{ __('Your price') }}</th><th><span class="sr-only">{{ __('Actions') }}</span></th></tr></thead>
                <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td><a href="{{ route('catalog.product', $row['variant']->product->slug) }}">{{ $row['name'] }}</a><div class="muted small">{{ $row['sku'] }}</div></td>
                        <td class="tnum">{{ Taka::format($row['standard_paisa']) }}{{ $row['unit'] === 'kg' ? '/kg' : '' }}</td>
                        <td class="tnum">
                            @foreach ($row['tiers'] as $tier)
                                <div><strong>{{ Taka::format($tier['rate_paisa']) }}{{ $row['unit'] === 'kg' ? '/kg' : '' }}</strong>
                                    @if ($tier['min_quantity'] > 1)<span class="muted small">{{ __('from :quantity', ['quantity' => $row['unit'] === 'kg' ? Weight::format($tier['min_quantity']) : $tier['min_quantity']]) }}</span>@endif
                                    @if ($tier['source'] === 'contract')<span class="status info">{{ __('contract') }}</span>@endif</div>
                            @endforeach
                        </td>
                        <td><a class="btn btn-ghost btn-sm" href="{{ route('catalog.product', $row['variant']->product->slug) }}">{{ __('View') }}</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
    <p class="hint">{{ __('Volume tiers apply to the total quantity of a product in your cart. Coupons do not stack with these prices unless an offer says so.') }}</p>
@endsection
