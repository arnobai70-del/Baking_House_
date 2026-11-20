@extends('layouts.app', ['title' => $type === \App\Enums\OrganizationType::Business ? __('Apply for a business account') : __('Apply as reseller')])

@section('content')
    @php $isBusiness = $type === \App\Enums\OrganizationType::Business; @endphp
    <form method="POST" action="{{ route('organizations.apply.store') }}" class="card card-pad reveal" style="max-width:560px;margin:0 auto" novalidate>
        @csrf
        <input type="hidden" name="type" value="{{ $type->value }}">
        <h1 style="font-size:var(--bh-fs-2xl)">{{ $isBusiness ? __('Apply for a business account') : __('Apply as reseller') }}</h1>
        <p class="muted small">{{ __('Manual review by our team. Protected pricing is enabled only after approval.') }}</p>
        <div class="field">
            <label for="name">{{ $isBusiness ? __('Business name') : __('Shop / business name') }}</label>
            <input class="input" id="name" name="name" value="{{ old('name') }}" required @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
            @error('name')<p class="field-error" id="name-error">{{ $message }}</p>@enderror
        </div>
        <div class="form-grid">
            <div class="field">
                <label for="business_category">{{ __('Category') }}</label>
                <select class="input" id="business_category" name="business_category">
                    @foreach (['Restaurant', 'Chinese restaurant', 'Café', 'Bakery', 'Caterer / cloud kitchen', 'Hotel', 'Retail shop', 'Distributor', 'Other'] as $category)
                        <option value="{{ $category }}" @selected(old('business_category') === $category)>{{ __($category) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="trade_licence_no">{{ __('Trade licence no. (optional)') }}</label>
                <input class="input" id="trade_licence_no" name="trade_licence_no" value="{{ old('trade_licence_no') }}">
            </div>
        </div>
        <div class="field">
            <label for="contact_phone">{{ __('Contact mobile') }}</label>
            <input class="input" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', auth()->user()->phone) }}" inputmode="tel" placeholder="01XXXXXXXXX" required @error('contact_phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
            @error('contact_phone')<p class="field-error" id="phone-error">{{ $message }}</p>@enderror
        </div>
        <button class="btn btn-primary" type="submit">{{ __('Submit application') }}</button>
    </form>
@endsection
