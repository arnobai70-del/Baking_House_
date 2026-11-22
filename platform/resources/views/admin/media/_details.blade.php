{{-- Provenance / licence / alt-text fields shared by upload and edit. $image may be null. --}}
@php $key = $image?->id ?? 'new'; @endphp
<div class="form-grid">
    <div class="field"><label for="source-{{ $key }}">Image source</label>
        <select class="input" id="source-{{ $key }}" name="source_type" required>
            <option value="">Choose…</option>
            @foreach ($sources as $source)<option value="{{ $source->value }}" @selected(old('source_type', $image?->source_type?->value) === $source->value)>{{ $source->label() }}</option>@endforeach
        </select></div>
    <div class="field"><label for="creator-{{ $key }}">Photographer / rights holder</label><input class="input" id="creator-{{ $key }}" name="creator" value="{{ old('creator', $image?->creator) }}" required maxlength="120"></div>
    <div class="field"><label for="license-{{ $key }}">Licence type</label><input class="input" id="license-{{ $key }}" name="license_type" value="{{ old('license_type', $image?->license_type) }}" placeholder="e.g. Owned, Commercial licence, Supplier permission letter" required maxlength="60"></div>
    <div class="field"><label for="ref-{{ $key }}">Licence reference / invoice no.</label><input class="input" id="ref-{{ $key }}" name="license_reference" value="{{ old('license_reference', $image?->license_reference) }}" maxlength="160"></div>
    <div class="field"><label for="expires-{{ $key }}">Licence expires (optional)</label><input class="input" id="expires-{{ $key }}" name="license_expires_at" type="date" value="{{ old('license_expires_at', $image?->license_expires_at?->format('Y-m-d')) }}"></div>
    <div class="field"><label for="url-{{ $key }}">Source URL (optional)</label><input class="input" id="url-{{ $key }}" name="source_url" type="url" value="{{ old('source_url', $image?->source_url) }}" maxlength="500"></div>
    <div class="field full"><label for="alt-{{ $key }}">Alt text (English) — describe what the photo shows</label><input class="input" id="alt-{{ $key }}" name="alt_text" value="{{ old('alt_text', $image?->alt_text) }}" required minlength="5" maxlength="160" placeholder="e.g. 1 kg bag of dark compound chocolate buttons"></div>
    <div class="field full"><label for="altbn-{{ $key }}">Alt text (Bangla, optional)</label><input class="input" id="altbn-{{ $key }}" name="alt_text_bn" value="{{ old('alt_text_bn', $image?->alt_text_bn) }}" maxlength="160" lang="bn"></div>
    @if ($product->variants->count() > 1)
        <div class="field"><label for="variant-{{ $key }}">Shows variant (optional)</label>
            <select class="input" id="variant-{{ $key }}" name="product_variant_id"><option value="">All variants</option>
                @foreach ($product->variants as $variant)<option value="{{ $variant->id }}" @selected((int) old('product_variant_id', $image?->product_variant_id) === $variant->id)>{{ $variant->sku }} {{ $variant->name }}</option>@endforeach
            </select></div>
    @endif
    <div class="field full"><label class="row" style="gap:8px;align-items:flex-start"><input type="checkbox" name="attested" value="1" @checked(old('attested', $image?->authenticity_attested)) style="margin-top:4px">
        <span>I confirm this is a <strong>real photograph</strong> of this exact product (or a licensed real photograph), <strong>not AI-generated or AI-altered</strong>, and that {{ config('brand.name') }} may use it.</span></label></div>
</div>
