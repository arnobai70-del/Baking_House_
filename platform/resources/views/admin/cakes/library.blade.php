@extends('layouts.admin', ['title' => 'Cake design library'])

@use('App\Support\Taka')
@use('App\Models\CakeLibraryItem')

@section('admin')
    <div class="admin-top"><h1>Cake design library</h1></div>
    @include('admin.cakes._tabs')

    <div class="alert alert-info" style="margin-bottom:16px"><x-icon name="info" /><span>
        Everything customers can add in the designer: templates, toppers, decorations, sticker packs, border piping, side patterns, fonts and colour palettes.
        Drawings must be our own simple vector shapes: no cartoon characters, brand logos or artwork we do not own. The price add-on is charged once per cake that uses the item.
        New items and price changes stay <strong>TEST</strong> until the owner approves them.</span></div>

    <form method="GET" class="row" style="gap:8px;flex-wrap:wrap;margin-bottom:12px">
        <label class="sr-only" for="lib-q">Search</label>
        <input class="input" id="lib-q" name="q" value="{{ $q }}" placeholder="Search name, code or tag" style="max-width:260px">
        <label class="sr-only" for="lib-kind">Kind</label>
        <select class="input" id="lib-kind" name="kind" style="max-width:220px">
            <option value="">All kinds</option>
            @foreach (CakeLibraryItem::KINDS as $value => $label)<option value="{{ $value }}" @selected($kind === $value)>{{ $label }}</option>@endforeach
        </select>
        <button class="btn btn-outline btn-sm" type="submit">Filter</button>
    </form>
    <div class="row" style="gap:6px;flex-wrap:wrap;margin-bottom:12px">
        @foreach (CakeLibraryItem::KINDS as $value => $label)
            <a class="btn btn-navy btn-sm" href="{{ route('admin.cakes.library.create', ['kind' => $value]) }}"><x-icon name="plus" /> {{ $label }}</a>
        @endforeach
    </div>

    @php $canApprove = auth()->user()?->can('prices.approve'); @endphp
    <form method="POST" action="{{ route('admin.cakes.library.approve') }}" id="lib-approve">@csrf</form>
    <div class="card"><div class="table-wrap"><table class="table">
        <thead><tr>@if ($canApprove)<th><span class="sr-only">Select</span></th>@endif<th>Item</th><th>Kind</th><th>Tags</th><th>Add-on</th><th>Shapes</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($items as $item)
            @php $p = $item->payload; $shape = $item->kind === 'sticker_pack' ? ($p['stickers'][0] ?? null) : (isset($p['paths']) ? $p : null); @endphp
            <tr>
                @if ($canApprove)<td>@if ($item->is_test)<input type="checkbox" name="items[]" value="{{ $item->id }}" form="lib-approve" aria-label="Approve {{ $item->name }}">@endif</td>@endif
                <td><div class="row" style="gap:10px;align-items:center">
                    <span class="lib-thumb" aria-hidden="true">
                        @if ($shape)
                            <svg viewBox="{{ $shape['viewBox'] }}">@foreach ($shape['paths'] as $path)<path d="{{ $path['d'] }}" fill="{{ in_array($path['fill'], ['accent', 'base'], true) ? '#e86f9a' : $path['fill'] }}"/>@endforeach</svg>
                        @elseif ($item->kind === 'palette')
                            <span class="swatches">@foreach ($p['colours'] as $c)<i style="background:{{ $c }}"></i>@endforeach</span>
                        @elseif ($item->kind === 'font')
                            <span style="font-family:{{ $p['stack'] }};font-weight:{{ $p['weight'] }}">Aa অ</span>
                        @else
                            <span class="small muted">{{ $p['style'] ?? (count($p['layers'] ?? []).' layers') }}</span>
                        @endif
                    </span>
                    <span><strong>{{ $item->name }}</strong>@if ($item->name_bn)<span class="small muted" lang="bn"> · {{ $item->name_bn }}</span>@endif<br><code class="small">{{ $item->code }}</code></span>
                </div></td>
                <td class="small">{{ CakeLibraryItem::KINDS[$item->kind] }}</td>
                <td class="small">{{ implode(', ', (array) $item->tags) }}</td>
                <td class="tnum">{{ $item->price_paisa ? Taka::format($item->price_paisa) : 'Free' }}</td>
                <td class="small">{{ $item->allowed_shapes ? implode(', ', $item->allowed_shapes) : 'All' }}</td>
                <td><span class="status {{ $item->is_test ? 'warn' : 'ok' }}">{{ $item->is_test ? 'TEST' : 'Approved' }}</span>@unless ($item->is_active) <span class="status">Off</span>@endunless</td>
                <td><a class="btn btn-outline btn-sm" href="{{ route('admin.cakes.library.edit', $item) }}">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty-state"><p><strong>No library items</strong></p><p class="muted small">Add a template, topper or palette above.</p></div></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
    @if ($canApprove && $items->contains('is_test', true))
        <div class="row" style="gap:8px;margin-top:12px"><button class="btn btn-teal btn-sm" form="lib-approve" type="submit">Approve selected prices</button>
            <span class="muted small">Recorded in the audit log.</span></div>
    @elseif (! $canApprove)
        <p class="muted small" style="margin-top:12px">Only the owner (super admin) can approve TEST prices.</p>
    @endif
@endsection
