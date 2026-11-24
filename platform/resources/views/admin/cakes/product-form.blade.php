@extends('layouts.admin', ['title' => 'Cake rules'])

@section('admin')
    <div class="admin-top"><h1>Cake rules: {{ $cake->product->name }}</h1></div>
    @include('admin.cakes._tabs')

    <form method="POST" action="{{ route('admin.cakes.products.update', $cake) }}" class="stack" novalidate>
        @csrf @method('PUT')
        <section class="card card-pad">
            <h2 style="font-size:var(--bh-fs-md)">Size</h2>
            <div class="form-grid">
                <div class="field"><label for="min_grams">Smallest (grams)</label><input class="input tnum" id="min_grams" name="min_grams" type="number" min="250" value="{{ old('min_grams', $cake->min_grams) }}">@error('min_grams')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="max_grams">Largest (grams)</label><input class="input tnum" id="max_grams" name="max_grams" type="number" min="250" value="{{ old('max_grams', $cake->max_grams) }}">@error('max_grams')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="step_grams">Step (grams)</label><input class="input tnum" id="step_grams" name="step_grams" type="number" min="100" value="{{ old('step_grams', $cake->step_grams) }}"></div>
                <div class="field"><label for="max_tiers">Most tiers</label><select class="input" id="max_tiers" name="max_tiers">@foreach ([1, 2, 3] as $n)<option value="{{ $n }}" @selected((int) old('max_tiers', $cake->max_tiers) === $n)>{{ $n }}</option>@endforeach</select>
                    <span class="hint">Each extra tier also needs a “Tiers” option with its price.</span></div>
            </div>
        </section>

        <section class="card card-pad">
            <h2 style="font-size:var(--bh-fs-md)">Lead time &amp; payment</h2>
            <div class="form-grid">
                <div class="field"><label for="lead_time_hours">Standard lead time for this cake (hours)</label><input class="input tnum" id="lead_time_hours" name="lead_time_hours" type="number" min="1" value="{{ old('lead_time_hours', $cake->lead_time_hours) }}">
                    <span class="hint">Empty = global setting. Complex designs still need the complex lead time.</span></div>
                <div class="field"><label for="advance_percent">Minimum advance (%)</label><input class="input tnum" id="advance_percent" name="advance_percent" type="number" min="0" max="100" value="{{ old('advance_percent', $cake->advance_percent) }}">
                    <span class="hint">Empty = global setting. The higher of the two applies.</span></div>
            </div>
            <label class="check"><input type="hidden" name="full_payment_required" value="0"><input type="checkbox" name="full_payment_required" value="1" @checked(old('full_payment_required', $cake->full_payment_required))> Full payment required at checkout</label>
        </section>

        <section class="card card-pad">
            <h2 style="font-size:var(--bh-fs-md)">Dietary options</h2>
            <p class="hint">Offer these only after the recipe is verified. Each needs a “Dietary option” price.</p>
            <label class="check"><input type="hidden" name="allow_eggless" value="0"><input type="checkbox" name="allow_eggless" value="1" @checked(old('allow_eggless', $cake->allow_eggless))> Eggless available</label>
            <label class="check"><input type="hidden" name="allow_sugar_free" value="0"><input type="checkbox" name="allow_sugar_free" value="1" @checked(old('allow_sugar_free', $cake->allow_sugar_free))> Sugar-free available</label>
            <label class="check"><input type="hidden" name="allow_both_diets" value="0"><input type="checkbox" name="allow_both_diets" value="1" @checked(old('allow_both_diets', $cake->allow_both_diets))> Eggless and sugar-free together</label>
        </section>

        <section class="card card-pad">
            <h2 style="font-size:var(--bh-fs-md)">Ingredients &amp; allergens (shown to customers)</h2>
            <div class="field"><label for="ingredients">Ingredients</label><textarea class="input" id="ingredients" name="ingredients" rows="3">{{ old('ingredients', $cake->ingredients) }}</textarea></div>
            <div class="field"><label for="allergens">Contains / may contain</label><textarea class="input" id="allergens" name="allergens" rows="2">{{ old('allergens', $cake->allergens) }}</textarea></div>
            <p class="hint">The designer always adds: “We cannot guarantee allergen-free preparation.”</p>
        </section>

        <div class="row" style="gap:8px"><button class="btn btn-navy" type="submit">Save rules</button><a class="btn btn-outline" href="{{ route('admin.cakes.products') }}">Back</a></div>
    </form>
@endsection
