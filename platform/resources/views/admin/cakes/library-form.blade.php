@extends('layouts.admin', ['title' => $item->exists ? 'Edit library item' : 'New library item'])

@use('App\Support\Taka')
@use('App\Models\CakeLibraryItem')
@use('App\Models\CakeOption')
@use('App\Services\Cakes\CakeLibrary')

@section('admin')
    <div class="admin-top"><h1>{{ $item->exists ? 'Edit: '.$item->name : 'New '.strtolower(CakeLibraryItem::KINDS[$item->kind]) }}</h1></div>
    @include('admin.cakes._tabs')

    <form method="POST" action="{{ $item->exists ? route('admin.cakes.library.update', $item) : route('admin.cakes.library.store') }}" class="stack" novalidate>
        @csrf @if ($item->exists) @method('PUT') @endif
        <input type="hidden" name="kind" value="{{ $item->kind }}">
        <section class="card card-pad">
            <p class="small muted" style="margin-top:0">Kind: <strong>{{ CakeLibraryItem::KINDS[$item->kind] }}</strong></p>
            <div class="form-grid">
                <div class="field"><label for="code">Code</label><input class="input" id="code" name="code" value="{{ old('code', $item->code) }}" required>
                    <span class="hint">Lowercase letters, numbers, - or _. Used in saved designs; avoid changing it later.</span>@error('code')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="price">Price add-on (৳)</label><input class="input tnum" id="price" name="price" value="{{ old('price', Taka::toInput($item->price_paisa ?? 0)) }}" required>@error('price')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="name">Name</label><input class="input" id="name" name="name" value="{{ old('name', $item->name) }}" required>@error('name')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="name_bn">Name (Bangla)</label><input class="input" id="name_bn" name="name_bn" lang="bn" value="{{ old('name_bn', $item->name_bn) }}"></div>
                <div class="field full"><label for="tags">Tags (comma separated, English or Bangla)</label><input class="input" id="tags" name="tags" value="{{ old('tags', implode(', ', (array) $item->tags)) }}" placeholder="birthday, kids, জন্মদিন"></div>
                <div class="field"><label for="complexity">Complexity (sets the lead time)</label><select class="input" id="complexity" name="complexity">
                    @foreach (CakeOption::COMPLEXITIES as $value => $label)<option value="{{ $value }}" @selected(old('complexity', $item->complexity) === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="field"><label for="sort_order">Order</label><input class="input tnum" id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $item->sort_order ?? 0) }}"></div>
            </div>
            <fieldset style="border:0;padding:0;margin:8px 0"><legend style="font-weight:700;color:var(--bh-navy)">Allowed shapes (none ticked = every shape)</legend>
                <div class="row" style="gap:12px;flex-wrap:wrap">
                    @foreach ($shapes as $shape)
                        <label class="check"><input type="checkbox" name="allowed_shapes[]" value="{{ $shape->code }}" @checked(in_array($shape->code, old('allowed_shapes', $item->allowed_shapes ?? []), true))> {{ $shape->name }}</label>
                    @endforeach
                </div>
            </fieldset>
            <label class="check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active))> Offered to customers</label>
        </section>

        <section class="card card-pad">
            <h2 style="font-size:var(--bh-fs-md)">Drawing data</h2>
            <p class="small muted">
                @switch ($item->kind)
                    @case ('topper') @case ('decoration')
                        A viewBox and up to 12 SVG paths. Each path has plain path data (M, L, C, Q, A, Z and numbers) and a fill: a #hex colour, <code>accent</code> (the customer's accent colour) or <code>base</code>. Draw your own simple shapes only.
                        @break
                    @case ('sticker_pack')
                        Up to 24 stickers, each with a name, a viewBox and paths (as for toppers). The pack's add-on is charged once, however many of its stickers are used.
                        @break
                    @case ('border')
                        A style ({{ implode(', ', CakeLibrary::BORDER_STYLES) }}) and a colour (<code>accent</code> or #hex).
                        @break
                    @case ('pattern')
                        A style ({{ implode(', ', CakeLibrary::PATTERN_STYLES) }}) and a colour (<code>accent</code> or #hex).
                        @break
                    @case ('font')
                        A family name, a CSS font stack (installed on the customer's device or served by our site; no external font services), a weight and the scripts it supports (en, bn).
                        @break
                    @case ('palette')
                        2–8 colours written like #f4c2c2. Colours from the chosen palette cost nothing extra.
                        @break
                    @default
                        A list of layers (text and library items only, never photos). x / y / w are fractions of the cake top's width; text size likewise.
                @endswitch
            </p>
            <div class="field"><label for="payload">JSON</label>
                <textarea class="input" id="payload" name="payload" rows="14" spellcheck="false" style="font-family:ui-monospace,Consolas,monospace;font-size:13px">{{ old('payload', $payloadJson) }}</textarea>
                @error('payload')<span class="field-error">{{ $message }}</span>@enderror</div>
        </section>

        <div class="row" style="gap:8px"><button class="btn btn-navy" type="submit">Save item</button><a class="btn btn-outline" href="{{ route('admin.cakes.library') }}">Back</a></div>
    </form>
@endsection
