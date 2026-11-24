@extends('layouts.admin', ['title' => 'Custom cake settings'])

@use('App\Support\Taka')

@section('admin')
    <div class="admin-top"><h1>Custom cake settings</h1></div>
    @include('admin.cakes._tabs')

    @if ($testOptions > 0)
        <div class="alert alert-info" style="margin-bottom:16px"><x-icon name="info" /><span>{{ $testOptions }} active cake price(s) are still TEST prices. Checkout of those cakes is blocked outside local development until the owner approves them under <a href="{{ route('admin.cakes.options') }}">Options &amp; prices</a>.</span></div>
    @endif

    <form method="POST" action="{{ route('admin.cakes.settings.update') }}" class="stack" novalidate>
        @csrf @method('PUT')
        <section class="card card-pad">
            <h2 style="font-size:var(--bh-fs-md)">Taking orders</h2>
            <label class="check"><input type="hidden" name="orders_open" value="0"><input type="checkbox" name="orders_open" value="1" @checked(old('orders_open', $values['cake.orders_open']))> Accept custom cake orders</label>
            <div class="form-grid" style="margin-top:8px">
                <div class="field"><label for="daily_capacity">Cakes per day (capacity)</label><input class="input tnum" id="daily_capacity" name="daily_capacity" type="number" min="0" value="{{ old('daily_capacity', $values['cake.daily_capacity']) }}">
                    <span class="hint">Leave empty until production capacity is agreed. No dates are offered without it.</span>@error('daily_capacity')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="booking_window_days">Book up to (days ahead)</label><input class="input tnum" id="booking_window_days" name="booking_window_days" type="number" min="1" value="{{ old('booking_window_days', $values['cake.booking_window_days']) }}"></div>
                <div class="field full"><label for="time_slots">Delivery / pickup time slots (one per line)</label>
                    <textarea class="input" id="time_slots" name="time_slots" rows="3">{{ old('time_slots', implode("\n", (array) $values['cake.time_slots'])) }}</textarea>
                    @error('time_slots')<span class="field-error">{{ $message }}</span>@enderror</div>
            </div>
            <p class="hint">Close or open single dates on the <a href="{{ route('admin.cakes.calendar') }}">production calendar</a>.</p>
        </section>

        <section class="card card-pad">
            <h2 style="font-size:var(--bh-fs-md)">Lead time (hours before the slot starts)</h2>
            <div class="form-grid">
                <div class="field"><label for="lead_hours_standard">Standard cakes</label><input class="input tnum" id="lead_hours_standard" name="lead_hours_standard" type="number" min="1" value="{{ old('lead_hours_standard', $values['cake.lead_hours_standard']) }}"></div>
                <div class="field"><label for="lead_hours_complex">Tiered / complex designs</label><input class="input tnum" id="lead_hours_complex" name="lead_hours_complex" type="number" min="1" value="{{ old('lead_hours_complex', $values['cake.lead_hours_complex']) }}"></div>
                <div class="field"><label for="lead_hours_simple">Simple cakes</label><input class="input tnum" id="lead_hours_simple" name="lead_hours_simple" type="number" min="1" value="{{ old('lead_hours_simple', $values['cake.lead_hours_simple']) }}"></div>
            </div>
            <label class="check"><input type="hidden" name="simple_enabled" value="0"><input type="checkbox" name="simple_enabled" value="1" @checked(old('simple_enabled', $values['cake.simple_enabled']))> Use the shorter lead time for simple cakes</label>
            <div class="form-grid" style="margin-top:8px">
                <div class="field"><label for="rush_fee">Rush fee (৳, optional)</label><input class="input tnum" id="rush_fee" name="rush_fee" value="{{ old('rush_fee', Taka::toInput($values['cake.rush_fee_paisa'])) }}">@error('rush_fee')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="rush_window_hours">…for slots starting within (hours)</label><input class="input tnum" id="rush_window_hours" name="rush_window_hours" type="number" min="1" value="{{ old('rush_window_hours', $values['cake.rush_window_hours']) }}"></div>
            </div>
        </section>

        <section class="card card-pad">
            <h2 style="font-size:var(--bh-fs-md)">Advance &amp; payment</h2>
            <div class="form-grid">
                <div class="field"><label for="advance_percent">Advance due at checkout (%)</label><input class="input tnum" id="advance_percent" name="advance_percent" type="number" min="0" max="100" value="{{ old('advance_percent', $values['cake.advance_percent']) }}">
                    <span class="hint">0 = nothing now. 100 = full payment. Per-cake rules can ask for more.</span></div>
                <div class="field"><label for="advance_high_value_threshold">Orders of at least (৳, optional)</label><input class="input tnum" id="advance_high_value_threshold" name="advance_high_value_threshold" value="{{ old('advance_high_value_threshold', Taka::toInput($values['cake.advance_high_value_threshold_paisa'])) }}"></div>
                <div class="field"><label for="advance_high_value_percent">…pay this advance (%)</label><input class="input tnum" id="advance_high_value_percent" name="advance_high_value_percent" type="number" min="0" max="100" value="{{ old('advance_high_value_percent', $values['cake.advance_high_value_percent']) }}"></div>
            </div>
            <label class="check"><input type="hidden" name="cod_enabled" value="0"><input type="checkbox" name="cod_enabled" value="1" @checked(old('cod_enabled', $values['cake.cod_enabled']))> Allow cash on delivery when no advance applies (the balance can always be collected in cash)</label>
            <div class="field" style="margin-top:8px"><label for="cancellation_policy">Cancellation &amp; refund policy shown at checkout</label>
                <textarea class="input" id="cancellation_policy" name="cancellation_policy" rows="3" placeholder="e.g. Free cancellation up to 24 hours before the slot. After that the advance is not refundable.">{{ old('cancellation_policy', $values['cake.cancellation_policy']) }}</textarea></div>
        </section>

        <div class="row"><button class="btn btn-navy" type="submit">Save settings</button></div>
    </form>
@endsection
