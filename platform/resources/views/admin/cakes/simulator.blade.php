@extends('layouts.admin', ['title' => 'Cake price simulator'])

@use('App\Support\Taka')
@use('App\Support\Weight')

@section('admin')
    <div class="admin-top"><h1>Cake price simulator</h1></div>
    @include('admin.cakes._tabs')

    @if ($cake === null)
        <div class="card card-pad"><p class="muted">Make a product designable under <a href="{{ route('admin.cakes.products') }}">Cake products</a> first.</p></div>
    @else
        <form method="GET" class="card card-pad" style="margin-bottom:16px">
            <p class="muted small">Runs the same server pricer the designer and checkout use. Nothing is saved.</p>
            <div class="form-grid">
                <div class="field"><label for="cake">Cake</label><select class="input" id="cake" name="cake">@foreach ($cakes as $choice)<option value="{{ $choice->id }}" @selected($choice->id === $cake->id)>{{ $choice->product->name }}</option>@endforeach</select></div>
                <div class="field"><label for="grams">Weight</label><select class="input" id="grams" name="grams">@foreach ($cake->weightChoices() as $grams)<option value="{{ $grams }}" @selected($spec['grams'] === $grams)>{{ Weight::format($grams) }}</option>@endforeach</select></div>
                <div class="field"><label for="tiers">Tiers</label><select class="input" id="tiers" name="tiers">@for ($n = 1; $n <= $cake->max_tiers; $n++)<option value="{{ $n }}" @selected($spec['tiers'] === $n)>{{ $n }}</option>@endfor</select></div>
                @foreach (['flavour' => 'Flavour', 'shape' => 'Shape', 'filling' => 'Filling', 'cream' => 'Cream'] as $group => $label)
                    <div class="field"><label for="sim-{{ $group }}">{{ $label }}</label><select class="input" id="sim-{{ $group }}" name="{{ $group }}">
                        @if (in_array($group, ['filling', 'cream'], true))<option value="">Standard (included)</option>@endif
                        @foreach ($options->get($group, []) as $option)<option value="{{ $option->code }}" @selected($spec[$group] === $option->code)>{{ $option->name }}</option>@endforeach
                    </select></div>
                @endforeach
            </div>
            <div class="row" style="gap:16px;flex-wrap:wrap;margin:4px 0 12px">
                @foreach ($options->get('diet', []) as $option)<label class="check"><input type="checkbox" name="diets[]" value="{{ $option->code }}" @checked(in_array($option->code, $spec['diets'], true))> {{ $option->name }}</label>@endforeach
                @if ($options->has('message'))<label class="check"><input type="checkbox" name="message" value="1" @checked($spec['message'] !== '')> Message</label>@endif
                @if ($options->has('print'))<label class="check"><input type="checkbox" name="print" value="1" @checked($spec['print'])> Edible print</label>@endif
                @foreach ($options->get('extra', []) as $option)<label class="check"><input type="checkbox" name="extras[]" value="{{ $option->code }}" @checked(in_array($option->code, $spec['extras'], true))> {{ $option->name }}</label>@endforeach
            </div>
            <button class="btn btn-navy" type="submit">Calculate</button>
        </form>

        @if ($priced)
            <div class="card card-pad">
                <h2 style="font-size:var(--bh-fs-lg)">{{ $cake->product->name }} — {{ Weight::format($spec['grams']) }}</h2>
                @if ($priced['error'])<div class="alert {{ $priced['ok'] ? 'alert-info' : 'alert-error' }}" style="margin-bottom:12px"><x-icon name="alert" /><span>{{ $priced['error'] }}</span></div>@endif
                <table class="specs"><tbody>
                    @foreach ($priced['breakdown'] as $part)<tr><th>{{ $part['label'] }}</th><td class="tnum">{{ Taka::format($part['paisa']) }}</td></tr>@endforeach
                    <tr><th><strong>Cake price</strong></th><td class="tnum"><strong>{{ Taka::format($priced['line_paisa']) }}</strong></td></tr>
                    <tr><th>Complexity</th><td>{{ ucfirst($priced['complexity']) }} · lead time {{ $leadHours }} h</td></tr>
                    <tr><th>Advance at checkout</th><td class="tnum">{{ $advancePercent }}% ≈ {{ Taka::format(intdiv($priced['line_paisa'] * $advancePercent + 50, 100)) }} (before delivery)</td></tr>
                    <tr><th>Next bookable date</th><td>{{ $dates[0]['date'] ?? 'None (orders closed, capacity not set, or fully booked)' }}</td></tr>
                    <tr><th>Price status</th><td><span class="status {{ $priced['uses_test_prices'] ? 'warn' : 'ok' }}">{{ $priced['uses_test_prices'] ? 'Uses TEST prices' : 'All approved' }}</span></td></tr>
                </tbody></table>
                <p class="hint">Delivery is added at checkout from the customer's zone (cake charge where set).</p>
            </div>
        @endif
    @endif
@endsection
