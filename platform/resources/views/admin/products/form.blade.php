@extends('layouts.admin', ['title' => $product->exists ? 'Edit product' : 'New product'])

@use('App\Support\Taka')

@php
    $canPrice = auth()->user()->can('prices.manage');
    $selectedCategories = collect(old('category_ids', $product->exists ? $product->categories->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->all();
    $specText = old('specifications_text', collect($product->specifications ?? [])->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n"));

    $variantRows = old('variants');
    if ($variantRows === null) {
        $variantRows = $product->exists ? $product->variants->map(fn ($v) => [
            'id' => $v->id, 'sku' => $v->sku, 'name' => $v->name, 'pricing_mode' => $v->pricing_mode->value,
            'selling_unit' => $v->selling_unit->value, 'pack_size' => $v->pack_size,
            'price' => Taka::toInput($v->price_paisa), 'compare_at' => Taka::toInput($v->compare_at_paisa), 'per_kg' => Taka::toInput($v->per_kg_paisa),
            'min_grams' => $v->min_grams, 'max_grams' => $v->max_grams, 'step_grams' => $v->step_grams,
            'preset_grams' => implode(', ', $v->preset_grams ?? []), 'stock_quantity' => $v->stock_quantity,
            'low_stock_threshold' => $v->low_stock_threshold, 'is_active' => $v->is_active, 'min_order_quantity' => $v->min_order_quantity,
        ])->all() : [];
        $variantRows[] = ['id' => null, 'sku' => '', 'name' => 'Standard', 'pricing_mode' => 'fixed', 'selling_unit' => 'piece', 'is_active' => true, 'stock_quantity' => 0, 'min_grams' => 100, 'max_grams' => 25000, 'step_grams' => 50, 'preset_grams' => '100, 250, 500, 700, 900, 1000'];
    }
@endphp

@section('admin')
    <div class="admin-top">
        <h1>{{ $product->exists ? 'Edit: '.$product->name : 'New product' }}</h1>
        <span class="spacer"></span>
        @if ($product->exists)
            @can('media.manage')<a class="btn btn-outline btn-sm" href="{{ route('admin.media.product', $product) }}"><x-icon name="photo" /> Photos</a>@endcan
        @endif
        @if ($product->exists && $product->status === \App\Enums\ProductStatus::Active)
            <a class="btn btn-ghost btn-sm" href="{{ $product->url() }}" target="_blank" rel="noopener">View on store ↗</a>
        @endif
    </div>

    <form method="POST" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}" novalidate>
        @csrf
        @if ($product->exists) @method('PUT') @endif

        <section class="card card-pad" style="margin-bottom:16px">
            <h2 style="font-size:var(--bh-fs-lg)">Product details</h2>
            <div class="form-grid">
                <div class="field"><label for="name">Name (English)</label><input class="input" id="name" name="name" value="{{ old('name', $product->name) }}" required></div>
                <div class="field"><label for="name_bn">Name (Bangla, optional)</label><input class="input" id="name_bn" name="name_bn" lang="bn" value="{{ old('name_bn', $product->name_bn) }}"></div>
                <div class="field"><label for="slug">URL slug</label><input class="input" id="slug" name="slug" value="{{ old('slug', $product->slug) }}" placeholder="auto from name"></div>
                <div class="field"><label for="status">Status</label>
                    <select class="input" id="status" name="status">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $product->status?->value) === $status->value)>{{ ucfirst($status->value) }}{{ $status->value === 'active' ? ' (visible on store)' : '' }}</option>
                        @endforeach
                    </select></div>
                <div class="field"><label for="primary_category_id">Main category</label>
                    <select class="input" id="primary_category_id" name="primary_category_id" required>
                        <option value="">Choose…</option>
                        @foreach ($categories as $root)
                            <option value="{{ $root->id }}" @selected((int) old('primary_category_id', $product->primary_category_id) === $root->id)>{{ $root->name }}</option>
                            @foreach ($root->children as $child)
                                <option value="{{ $child->id }}" @selected((int) old('primary_category_id', $product->primary_category_id) === $child->id)>— {{ $child->name }}</option>
                            @endforeach
                        @endforeach
                    </select></div>
                <div class="field"><label for="brand_id">Brand</label>
                    <select class="input" id="brand_id" name="brand_id">
                        <option value="">No brand</option>
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}" @selected((int) old('brand_id', $product->brand_id) === $brand->id)>{{ $brand->name }}</option>
                        @endforeach
                    </select></div>
                <div class="field full"><label for="short_description">Short description</label><input class="input" id="short_description" name="short_description" value="{{ old('short_description', $product->short_description) }}"></div>
                <div class="field full"><label for="short_description_bn">Short description (Bangla)</label><input class="input" id="short_description_bn" name="short_description_bn" lang="bn" value="{{ old('short_description_bn', $product->short_description_bn) }}"></div>
                <div class="field full"><label for="description">Full description</label><textarea class="input" id="description" name="description" rows="4">{{ old('description', $product->description) }}</textarea></div>
                <div class="field full"><label for="description_bn">Full description (Bangla)</label><textarea class="input" id="description_bn" name="description_bn" lang="bn" rows="3">{{ old('description_bn', $product->description_bn) }}</textarea></div>
                <div class="field"><label for="specifications_text">Specifications</label><textarea class="input" id="specifications_text" name="specifications_text" rows="4" placeholder="Material: Stainless steel&#10;Capacity: 9 L">{{ $specText }}</textarea><span class="hint">One per line as “Label: value”.</span></div>
                <div class="field"><label for="search_keywords">Search keywords &amp; synonyms</label><textarea class="input" id="search_keywords" name="search_keywords" rows="4" placeholder="cake mould, mold, কেক মোল্ড">{{ old('search_keywords', $product->search_keywords) }}</textarea><span class="hint">Bangla and alternative spellings help search.</span></div>
            </div>
            <fieldset style="border:0;padding:0;margin:0 0 10px"><legend class="small" style="font-weight:600;color:var(--bh-navy)">Also show in categories</legend>
                <div class="row" style="gap:4px 16px">
                    @foreach ($categories as $root)
                        <label class="check"><input type="checkbox" name="category_ids[]" value="{{ $root->id }}" @checked(in_array($root->id, $selectedCategories, true))> {{ $root->name }}</label>
                        @foreach ($root->children as $child)
                            <label class="check"><input type="checkbox" name="category_ids[]" value="{{ $child->id }}" @checked(in_array($child->id, $selectedCategories, true))> {{ $root->name }} › {{ $child->name }}</label>
                        @endforeach
                    @endforeach
                </div>
            </fieldset>
            <label class="check"><input type="hidden" name="is_featured" value="0"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured))> Featured on the home page</label>
            <div class="form-grid" style="margin-top:10px">
                <div class="field"><label for="seo_title">SEO title (optional)</label><input class="input" id="seo_title" name="seo_title" value="{{ old('seo_title', $product->seo_title) }}"></div>
                <div class="field"><label for="seo_description">SEO description (optional)</label><input class="input" id="seo_description" name="seo_description" value="{{ old('seo_description', $product->seo_description) }}"></div>
            </div>
        </section>

        <section class="card card-pad" style="margin-bottom:16px">
            <h2 style="font-size:var(--bh-fs-lg)">Variants, units &amp; prices</h2>
            <p class="muted small">Prices in taka (e.g. 650 or 812.50). Weight items: price per kg, stock and rules in grams. Leave the last empty SKU blank to skip adding a variant.
                @unless ($canPrice)<strong>You can view prices but not change them.</strong>@endunless</p>

            @foreach ($variantRows as $i => $row)
                <fieldset class="variant-row">
                    <legend>{{ ! empty($row['id']) ? 'Variant: '.($row['sku'] ?? '') : 'Add a variant' }}</legend>
                    <input type="hidden" name="variants[{{ $i }}][id]" value="{{ $row['id'] ?? '' }}">
                    <div class="variant-grid">
                        <div class="field"><label for="v{{ $i }}-sku">SKU</label><input class="input" id="v{{ $i }}-sku" name="variants[{{ $i }}][sku]" value="{{ $row['sku'] ?? '' }}" placeholder="BH-ING-0001">
                            @error("variants.$i.sku")<span class="field-error">{{ $message }}</span>@enderror</div>
                        <div class="field"><label for="v{{ $i }}-name">Option name</label><input class="input" id="v{{ $i }}-name" name="variants[{{ $i }}][name]" value="{{ $row['name'] ?? 'Standard' }}"></div>
                        <div class="field"><label for="v{{ $i }}-mode">Pricing</label>
                            <select class="input" id="v{{ $i }}-mode" name="variants[{{ $i }}][pricing_mode]">
                                @foreach ($pricingModes as $mode)
                                    <option value="{{ $mode->value }}" @selected(($row['pricing_mode'] ?? 'fixed') === $mode->value)>{{ $mode->label() }}</option>
                                @endforeach
                            </select></div>
                        <div class="field"><label for="v{{ $i }}-unit">Selling unit</label>
                            <select class="input" id="v{{ $i }}-unit" name="variants[{{ $i }}][selling_unit]">
                                @foreach ($sellingUnits as $unit)
                                    <option value="{{ $unit->value }}" @selected(($row['selling_unit'] ?? 'piece') === $unit->value)>{{ $unit->label() }}</option>
                                @endforeach
                            </select>
                            @error("variants.$i.selling_unit")<span class="field-error">{{ $message }}</span>@enderror</div>

                        <div class="field"><label for="v{{ $i }}-price">Price per unit (৳) — fixed</label><input class="input tnum" id="v{{ $i }}-price" name="variants[{{ $i }}][price]" value="{{ $row['price'] ?? '' }}" @readonly(! $canPrice)>
                            @error("variants.$i.price")<span class="field-error">{{ $message }}</span>@enderror</div>
                        <div class="field"><label for="v{{ $i }}-compare">Was price (৳, optional)</label><input class="input tnum" id="v{{ $i }}-compare" name="variants[{{ $i }}][compare_at]" value="{{ $row['compare_at'] ?? '' }}" @readonly(! $canPrice)></div>
                        <div class="field"><label for="v{{ $i }}-pack">Units per pack/box/carton</label><input class="input tnum" id="v{{ $i }}-pack" name="variants[{{ $i }}][pack_size]" type="number" min="1" value="{{ $row['pack_size'] ?? '' }}">
                            @error("variants.$i.pack_size")<span class="field-error">{{ $message }}</span>@enderror</div>
                        <div class="field"><label for="v{{ $i }}-perkg">Price per kg (৳) — weight</label><input class="input tnum" id="v{{ $i }}-perkg" name="variants[{{ $i }}][per_kg]" value="{{ $row['per_kg'] ?? '' }}" @readonly(! $canPrice)>
                            @error("variants.$i.per_kg")<span class="field-error">{{ $message }}</span>@enderror</div>

                        <div class="field"><label for="v{{ $i }}-min">Min grams</label><input class="input tnum" id="v{{ $i }}-min" name="variants[{{ $i }}][min_grams]" type="number" min="1" value="{{ $row['min_grams'] ?? '' }}"></div>
                        <div class="field"><label for="v{{ $i }}-step">Step grams</label><input class="input tnum" id="v{{ $i }}-step" name="variants[{{ $i }}][step_grams]" type="number" min="1" value="{{ $row['step_grams'] ?? '' }}">
                            @error("variants.$i.step_grams")<span class="field-error">{{ $message }}</span>@enderror</div>
                        <div class="field"><label for="v{{ $i }}-max">Max grams</label><input class="input tnum" id="v{{ $i }}-max" name="variants[{{ $i }}][max_grams]" type="number" min="1" value="{{ $row['max_grams'] ?? '' }}">
                            @error("variants.$i.max_grams")<span class="field-error">{{ $message }}</span>@enderror</div>
                        <div class="field"><label for="v{{ $i }}-presets">Quick-pick grams</label><input class="input tnum" id="v{{ $i }}-presets" name="variants[{{ $i }}][preset_grams]" value="{{ is_array($row['preset_grams'] ?? null) ? implode(', ', $row['preset_grams']) : ($row['preset_grams'] ?? '') }}">
                            @error("variants.$i.preset_grams")<span class="field-error">{{ $message }}</span>@enderror</div>

                        @if (! empty($row['id']))
                            <div class="field"><span class="small" style="font-weight:600;color:var(--bh-navy)">Stock on hand</span><span class="tnum">{{ $row['stock_quantity'] ?? 0 }}</span>
                                <a class="hint" href="{{ route('admin.inventory.show', $row['id']) }}">Change via stock ledger →</a></div>
                        @else
                            <div class="field"><label for="v{{ $i }}-stock">Opening stock</label><input class="input tnum" id="v{{ $i }}-stock" name="variants[{{ $i }}][stock_quantity]" type="number" min="0" value="{{ $row['stock_quantity'] ?? 0 }}"><span class="hint">Grams for weight items, units otherwise. Later changes go through the stock ledger.</span></div>
                        @endif
                        <div class="field"><label for="v{{ $i }}-moq">Minimum order (units)</label><input class="input tnum" id="v{{ $i }}-moq" name="variants[{{ $i }}][min_order_quantity]" type="number" min="1" value="{{ $row['min_order_quantity'] ?? 1 }}"><span class="hint">Weight items use min grams.</span></div>
                        <div class="field"><label for="v{{ $i }}-low">Low-stock alert at</label><input class="input tnum" id="v{{ $i }}-low" name="variants[{{ $i }}][low_stock_threshold]" type="number" min="0" value="{{ $row['low_stock_threshold'] ?? 0 }}"></div>
                        <div class="field" style="justify-content:flex-end"><label class="check"><input type="hidden" name="variants[{{ $i }}][is_active]" value="0"><input type="checkbox" name="variants[{{ $i }}][is_active]" value="1" @checked($row['is_active'] ?? true)> Variant on sale</label></div>
                    </div>
                </fieldset>
            @endforeach

            @if ($product->exists)
                <div class="reason-box">
                    <label for="price_change_reason" style="font-weight:700;color:var(--bh-navy)">Reason for any price change</label>
                    <input class="input" id="price_change_reason" name="price_change_reason" value="{{ old('price_change_reason') }}" placeholder="e.g. Supplier cost increase from 1 Dec">
                    <span class="hint">Required when an existing price changes. Saved with before/after values in the audit log.</span>
                    @error('price_change_reason')<div class="field-error">{{ $message }}</div>@enderror
                </div>
            @endif
        </section>

        <div class="row">
            <button class="btn btn-primary" type="submit">{{ $product->exists ? 'Save & publish changes' : 'Create product' }}</button>
            <a class="btn btn-ghost" href="{{ route('admin.products.index') }}">Cancel</a>
        </div>
    </form>
@endsection
