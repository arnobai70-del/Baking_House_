@extends('portal.layout', ['heading' => __('Bulk order'), 'subheading' => __('Add many SKUs at once. Every line is checked against your prices, weight rules and stock.')])

@use('App\Support\Taka')

@section('portal')
    <div class="card" style="margin-bottom:16px">
        <div class="panel-title"><h2>{{ __('Add many SKUs at once') }}</h2></div>
        <form method="POST" action="{{ route($portal.'.bulk.submit') }}" enctype="multipart/form-data" class="card-pad" novalidate>
            @csrf
            <p class="muted small">{!! __('One line per item: <code>SKU, quantity</code>. Weight items take grams or kg (e.g. <code>BH-ING-0001, 6kg</code>); a bare number means grams.') !!}</p>
            <div class="field">
                <label for="lines">{{ __('Items') }}</label>
                <textarea class="input tnum" id="lines" name="lines" rows="8" placeholder="BH-ING-0001, 6kg&#10;BH-PKG-0002, 10" @error('lines') aria-invalid="true" @enderror>{{ $lines }}</textarea>
                @error('lines')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="file">{{ __('Or upload a CSV file') }} <span class="muted small">({{ __('columns: SKU, quantity; max 200 lines') }})</span></label>
                <input class="input" id="file" name="file" type="file" accept=".csv,text/csv,text/plain">
                @error('file')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <div class="row">
                <button class="btn btn-outline" type="submit" name="action" value="check">{{ __('Check prices & stock') }}</button>
                @if ($role->canPlaceOrders())
                    <button class="btn btn-primary" type="submit" name="action" value="add"><x-icon name="cart" /> {{ __('Add valid lines to cart') }}</button>
                @endif
            </div>
        </form>
    </div>

    @if ($results !== null)
        @php $valid = count(array_filter($results, fn ($row) => $row['ok'])); @endphp
        <div class="card" aria-live="polite">
            <div class="panel-title"><h2>{{ __('Check result') }}</h2><span class="small muted">{{ __(':ok of :total line(s) OK', ['ok' => $valid, 'total' => count($results)]) }}</span></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('Line') }}</th><th>{{ __('Product') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('Price') }}</th><th>{{ __('Result') }}</th></tr></thead>
                <tbody>
                @foreach ($results as $row)
                    <tr>
                        <td class="tnum small">{{ $row['line'] }}: {{ $row['input'] }}</td>
                        <td>{{ $row['name'] ?? '—' }}<div class="muted small">{{ $row['sku'] }}</div></td>
                        <td class="tnum">{{ $row['quantity_label'] ?? '—' }}</td>
                        <td class="tnum">{{ $row['line_paisa'] !== null ? Taka::format($row['line_paisa']) : ($row['ok'] ? __('Merged with other lines') : '—') }}
                            @if ($row['ok'] && ! in_array($row['rate_source'], ['standard', null], true))<span class="status info">{{ __($row['rate_source']) }}</span>@endif</td>
                        <td>@if ($row['ok'])<span class="status ok">{{ __('OK') }}</span>@else<span class="status warn">{{ $row['error'] }}</span>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
        <p class="hint">{{ __('Prices are previews; the cart and checkout re-check price and stock before you pay.') }}</p>
    @endif
@endsection
