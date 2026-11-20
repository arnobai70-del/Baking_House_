@php $key = $address?->id ?? 'new'; @endphp
<form method="POST" action="{{ $action }}" novalidate>
    @csrf
    @if ($method !== 'POST') @method($method) @endif
    <div class="form-grid">
        <div class="field"><label for="label-{{ $key }}">{{ __('Label') }}</label><input class="input" id="label-{{ $key }}" name="label" value="{{ $address?->label ?? old('label') }}" placeholder="{{ __('e.g. Gulshan branch') }}" required maxlength="60"></div>
        <div class="field"><label for="recipient-{{ $key }}">{{ __('Receiver name') }}</label><input class="input" id="recipient-{{ $key }}" name="recipient_name" value="{{ $address?->recipient_name ?? old('recipient_name') }}" required></div>
        <div class="field"><label for="phone-{{ $key }}">{{ __('Mobile') }}</label><input class="input" id="phone-{{ $key }}" name="phone" inputmode="tel" value="{{ $address?->phone ?? old('phone') }}" placeholder="01XXXXXXXXX" required></div>
        <div class="field"><label for="district-{{ $key }}">{{ __('District') }}</label>
            <select class="input" id="district-{{ $key }}" name="district" required>
                @foreach ($districts as $district)<option @selected(($address?->district ?? old('district', 'Dhaka')) === $district)>{{ $district }}</option>@endforeach
            </select></div>
        <div class="field full"><label for="line-{{ $key }}">{{ __('Address') }}</label><input class="input" id="line-{{ $key }}" name="line" value="{{ $address?->line ?? old('line') }}" required></div>
        <div class="field"><label for="area-{{ $key }}">{{ __('Area / thana') }}</label><input class="input" id="area-{{ $key }}" name="area" value="{{ $address?->area ?? old('area') }}" required></div>
        <div class="field"><label class="row" style="gap:6px;margin-top:28px"><input type="checkbox" name="is_default" value="1" @checked($address?->is_default)> {{ __('Default address') }}</label></div>
    </div>
    <button class="btn btn-teal btn-sm" type="submit">{{ __('Save address') }}</button>
</form>
