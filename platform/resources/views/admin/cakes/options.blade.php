@extends('layouts.admin', ['title' => 'Cake options & prices'])

@use('App\Support\Taka')
@use('App\Models\CakeOption')

@section('admin')
    <div class="admin-top"><h1>Cake options &amp; prices</h1></div>
    @include('admin.cakes._tabs')

    <div class="alert alert-info" style="margin-bottom:16px"><x-icon name="info" /><span>
        The cake price is the flavour's rate per kg × weight, plus each chosen option. New options and every price change start as <strong>TEST</strong>;
        live checkout only accepts cakes whose prices the owner has approved. Options for one cake override global options with the same code.</span></div>

    @php $canApprove = auth()->user()?->can('prices.approve'); @endphp
    <form method="POST" action="{{ route('admin.cakes.options.approve') }}" id="approve-form">@csrf</form>
    <div class="card" style="margin-bottom:16px"><div class="table-wrap"><table class="table">
        <thead><tr>@if ($canApprove)<th><span class="sr-only">Select</span></th>@endif<th>Option</th><th>Code</th><th>Price</th><th>Complexity</th><th>Applies to</th><th>Status</th><th></th></tr></thead>
        @forelse ($options as $group => $rows)
            <tbody>
                <tr><th colspan="{{ $canApprove ? 8 : 7 }}" style="background:var(--bh-surface-2, transparent)">{{ CakeOption::GROUPS[$group] ?? $group }}</th></tr>
                @foreach ($rows as $option)
                    <tr>
                        @if ($canApprove)<td>@if ($option->is_test)<input type="checkbox" name="options[]" value="{{ $option->id }}" form="approve-form" aria-label="Approve {{ $option->name }}">@endif</td>@endif
                        <td><strong>{{ $option->name }}</strong>@if ($option->name_bn)<div class="small muted" lang="bn">{{ $option->name_bn }}</div>@endif</td>
                        <td class="small"><code>{{ $option->code }}</code></td>
                        <td class="tnum">{{ Taka::format($option->price_paisa) }} <span class="small muted">{{ strtolower(CakeOption::PRICE_TYPES[$option->price_type] ?? $option->price_type) }}</span></td>
                        <td class="small">{{ CakeOption::COMPLEXITIES[$option->complexity] ?? $option->complexity }}@if ($option->requires_quote) · <strong>quote only</strong>@endif</td>
                        <td class="small">{{ $option->cakeProduct?->product?->name ?? 'All cakes' }}</td>
                        <td>
                            <span class="status {{ $option->is_test ? 'warn' : 'ok' }}">{{ $option->is_test ? 'TEST' : 'Approved' }}</span>
                            @unless ($option->is_active)<span class="status">Off</span>@endunless
                        </td>
                        <td><a class="btn btn-outline btn-sm" href="{{ route('admin.cakes.options', ['edit' => $option->id]) }}#option-form">Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        @empty
            <tbody><tr><td colspan="8"><div class="empty-state"><p><strong>No cake options yet</strong></p><p class="muted small">Add at least one flavour and one shape before the designer can price a cake.</p></div></td></tr></tbody>
        @endforelse
    </table></div></div>
    @if ($canApprove && $options->flatten()->contains('is_test', true))
        <div class="row" style="gap:8px;margin-bottom:24px">
            <button class="btn btn-teal btn-sm" form="approve-form" type="submit">Approve selected prices</button>
            <span class="muted small">Approving confirms these are the real prices customers will pay. It is recorded in the audit log.</span>
        </div>
    @elseif (! $canApprove)
        <p class="muted small" style="margin-bottom:24px">Only the owner (super admin) can approve TEST prices.</p>
    @endif

    <form method="POST" action="{{ $editing->exists ? route('admin.cakes.options.update', $editing) : route('admin.cakes.options.store') }}" class="card card-pad" id="option-form" novalidate>
        @csrf @if ($editing->exists) @method('PUT') @endif
        <h2 style="font-size:var(--bh-fs-md)">{{ $editing->exists ? 'Edit: '.$editing->name : 'Add an option' }}</h2>
        <div class="form-grid">
            <div class="field"><label for="group">Group</label><select class="input" id="group" name="group">
                @foreach (CakeOption::GROUPS as $value => $label)<option value="{{ $value }}" @selected(old('group', $editing->group) === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="code">Code</label><input class="input" id="code" name="code" value="{{ old('code', $editing->code) }}" placeholder="e.g. chocolate, heart, 2" required>
                <span class="hint">Lowercase letters, numbers, - or _. Tiers use the number of tiers (2, 3).</span>@error('code')<span class="field-error">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="name">Name</label><input class="input" id="name" name="name" value="{{ old('name', $editing->name) }}" required>@error('name')<span class="field-error">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="name_bn">Name (Bangla)</label><input class="input" id="name_bn" name="name_bn" lang="bn" value="{{ old('name_bn', $editing->name_bn) }}"></div>
            <div class="field"><label for="price">Price (৳)</label><input class="input tnum" id="price" name="price" value="{{ old('price', Taka::toInput($editing->price_paisa)) }}" required>@error('price')<span class="field-error">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="price_type">Charged</label><select class="input" id="price_type" name="price_type">
                @foreach (CakeOption::PRICE_TYPES as $value => $label)<option value="{{ $value }}" @selected(old('price_type', $editing->price_type) === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="complexity">Complexity (sets the lead time)</label><select class="input" id="complexity" name="complexity">
                @foreach (CakeOption::COMPLEXITIES as $value => $label)<option value="{{ $value }}" @selected(old('complexity', $editing->complexity) === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="cake_product_id">Applies to</label><select class="input" id="cake_product_id" name="cake_product_id">
                <option value="">All cakes</option>
                @foreach ($cakes as $cake)<option value="{{ $cake->id }}" @selected((int) old('cake_product_id', $editing->cake_product_id) === $cake->id)>{{ $cake->product->name }} only</option>@endforeach</select></div>
            <div class="field"><label for="sort_order">Order</label><input class="input tnum" id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $editing->sort_order ?? 0) }}"></div>
        </div>
        <label class="check"><input type="hidden" name="requires_quote" value="0"><input type="checkbox" name="requires_quote" value="1" @checked(old('requires_quote', $editing->requires_quote))> Needs a quotation (the customer cannot check out with this option)</label>
        <label class="check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editing->is_active))> Offered to customers</label>
        <div class="row" style="margin-top:12px;gap:8px"><button class="btn btn-navy" type="submit">{{ $editing->exists ? 'Save option' : 'Add option' }}</button>
            @if ($editing->exists)<a class="btn btn-outline" href="{{ route('admin.cakes.options') }}">Cancel</a>@endif</div>
    </form>
@endsection
