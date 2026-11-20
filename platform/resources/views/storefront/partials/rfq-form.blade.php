{{-- Quotation request (RFQ). Posts to the single server endpoint; see QuotationRequestController. --}}
<form method="POST" action="{{ route('quotations.store') }}" novalidate>
    @csrf
    <div class="field">
        <label for="rfq-items">{{ __('Items & quantities') }}</label>
        <textarea class="input" id="rfq-items" name="request_text" rows="4" required placeholder="{{ __('e.g. Deck oven 2-deck × 1, soy sauce cartons × 20, dark compound chocolate 25 kg') }}" @error('request_text') aria-invalid="true" @enderror>{{ old('request_text', $requestText ?? '') }}</textarea>
        @error('request_text')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="form-grid">
        <div class="field"><label for="rfq-name">{{ __('Name') }}</label><input class="input" id="rfq-name" name="contact_name" value="{{ old('contact_name', $prefill['name'] ?? '') }}" required autocomplete="name">
            @error('contact_name')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div class="field"><label for="rfq-phone">{{ __('Mobile') }}</label><input class="input" id="rfq-phone" name="contact_phone" inputmode="tel" value="{{ old('contact_phone', $prefill['phone'] ?? '') }}" required placeholder="01XXXXXXXXX" autocomplete="tel">
            @error('contact_phone')<p class="field-error">{{ $message }}</p>@enderror</div>
        <div class="field full"><label for="rfq-email">{{ __('Email (optional)') }}</label><input class="input" id="rfq-email" name="contact_email" type="email" value="{{ old('contact_email', $prefill['email'] ?? '') }}" autocomplete="email"></div>
        <div class="field full"><label for="rfq-note">{{ __('Delivery area or notes (optional)') }}</label><input class="input" id="rfq-note" name="customer_note" value="{{ old('customer_note') }}" maxlength="1000"></div>
    </div>
    <div class="sr-only" aria-hidden="true"><label for="rfq-website">Website</label><input id="rfq-website" name="website" tabindex="-1" autocomplete="off"></div>
    <button class="btn btn-primary" type="submit"><x-icon name="quote" /> {{ __('Send request') }}</button>
    <p class="hint" style="margin-top:8px">{{ __('Our sales team checks price and stock, then sends a quote. Nothing is charged until you accept a quote and check out.') }}</p>
</form>
