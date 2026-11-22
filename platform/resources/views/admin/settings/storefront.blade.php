@extends('layouts.admin', ['title' => 'Storefront languages'])

@section('admin')
    <div class="admin-top"><h1>Storefront languages</h1></div>
    <form method="POST" action="{{ route('admin.settings.storefront.update') }}" class="card card-pad" style="max-width:560px" novalidate>
        @csrf
        @method('PUT')
        <fieldset style="border:0;padding:0;margin:0 0 14px"><legend style="font-weight:700;color:var(--bh-navy)">Languages offered to shoppers</legend>
            @foreach ($languages as $code => $label)
                <label class="check"><input type="checkbox" name="languages[]" value="{{ $code }}" @checked(in_array($code, $enabled, true))> {{ $label }}</label>
            @endforeach
        </fieldset>
        <div class="field"><label for="default">Default language</label>
            <select class="input" id="default" name="default">
                @foreach ($languages as $code => $label)
                    <option value="{{ $code }}" @selected($default === $code)>{{ $label }}</option>
                @endforeach
            </select></div>
        <p class="hint">With more than one language enabled, shoppers see a language switch in the top bar. Bangla text comes from the “Bangla” fields on products, categories and bundles; anything not yet translated falls back to English.</p>
        <button class="btn btn-navy" type="submit">Save</button>
    </form>
@endsection
