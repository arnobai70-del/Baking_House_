@extends('layouts.app', ['title' => 'Apply — '.$type->label()])

@section('content')
    <form method="POST" action="{{ route('organizations.apply.store') }}" class="card card-pad" style="max-width:560px;margin:0 auto" novalidate>
        @csrf
        <input type="hidden" name="type" value="{{ $type->value }}">
        <h1 style="font-size:var(--bh-fs-2xl)">Apply: {{ $type->label() }}</h1>
        <p class="muted small">Manual review by our team. Protected pricing is enabled only after approval.</p>
        <div class="field">
            <label for="name">{{ $type === \App\Enums\OrganizationType::Business ? 'Business name' : 'Shop / business name' }}</label>
            <input class="input" id="name" name="name" value="{{ old('name') }}" required @error('name') aria-invalid="true" @enderror>
        </div>
        <div class="form-grid">
            <div class="field">
                <label for="business_category">Category</label>
                <select class="input" id="business_category" name="business_category">
                    @foreach (['Restaurant', 'Chinese restaurant', 'Café', 'Bakery', 'Caterer / cloud kitchen', 'Hotel', 'Retail shop', 'Distributor', 'Other'] as $category)
                        <option @selected(old('business_category') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="trade_licence_no">Trade licence no. (optional)</label>
                <input class="input" id="trade_licence_no" name="trade_licence_no" value="{{ old('trade_licence_no') }}">
            </div>
        </div>
        <div class="field">
            <label for="contact_phone">Contact mobile</label>
            <input class="input" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', auth()->user()->phone) }}" inputmode="tel" placeholder="01XXXXXXXXX" required @error('contact_phone') aria-invalid="true" @enderror>
        </div>
        <button class="btn btn-primary" type="submit">Submit application</button>
    </form>
@endsection
