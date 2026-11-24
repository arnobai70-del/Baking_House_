@use('App\Models\CakeUpload')
@php
    $design = $item->cake_design_id ? \App\Models\CakeDesign::query()->find($item->cake_design_id) : null;
    $layers = (array) ($item->components['spec']['canvas']['layers'] ?? []);
    $uploadUuids = array_values(array_unique(array_column(array_filter($layers, fn ($l) => ($l['type'] ?? '') === 'image'), 'upload')));
    $uploads = CakeUpload::query()->whereIn('uuid', $uploadUuids)->get();
    $pending = $uploads->where('review_status', CakeUpload::PENDING)->count();
@endphp
<div class="cake-admin card card-pad">
    <div class="row" style="justify-content:space-between;gap:8px;flex-wrap:wrap">
        <h3 style="margin:0">Custom cake · {{ $item->scheduled_for?->format('D j M Y') }} · {{ $item->time_slot }}</h3>
        <div class="row" style="gap:6px">
            @if ($uploads->isNotEmpty())
                <span class="status {{ $pending ? 'warn' : ($uploads->contains('review_status', CakeUpload::REJECTED) ? 'error' : 'ok') }}">
                    {{ $pending ? $pending.' print(s) awaiting approval' : ($uploads->contains('review_status', CakeUpload::REJECTED) ? 'Print rejected' : 'Prints approved') }}</span>
            @endif
            <a class="btn btn-navy btn-sm" href="{{ route('admin.orders.cake-sheet', [$order, $item]) }}">Baker job sheet</a>
        </div>
    </div>
    <div class="cake-admin-pics">
        @if ($design?->snapshot_path)
            <figure><img src="{{ route('cakes.design-image', [$design->uuid, 'snapshot']) }}" alt="3D snapshot of the ordered cake" width="240" height="240" loading="lazy"><figcaption class="small muted">3D snapshot</figcaption></figure>
        @endif
        @if ($design?->top_path)
            <figure><img src="{{ route('cakes.design-image', [$design->uuid, 'top']) }}" alt="Top design of the ordered cake" width="240" height="240" loading="lazy"><figcaption class="small muted">Top design</figcaption></figure>
        @endif
        @if (! $design?->snapshot_path && ! $design?->top_path)<p class="small muted">No preview pictures were saved with this design.</p>@endif
    </div>
    @foreach ($uploads as $upload)
        <div class="cake-print">
            <a href="{{ route('admin.cakes.uploads.show', $upload) }}" target="_blank" rel="noopener"><img src="{{ route('admin.cakes.uploads.show', $upload) }}" alt="Customer photo for the edible print" width="96" height="96" loading="lazy"></a>
            <div>
                <p class="small" style="margin:0 0 4px"><strong>Customer photo</strong> · {{ $upload->width }}×{{ $upload->height }} px · rights confirmed {{ $upload->rights_confirmed_at->timezone('Asia/Dhaka')->format('j M H:i') }}<br>
                    Status: <span class="status {{ ['approved' => 'ok', 'rejected' => 'error', 'pending' => 'warn'][$upload->review_status] }}">{{ ucfirst($upload->review_status) }}</span>
                    @if ($upload->review_note) · {{ $upload->review_note }}@endif</p>
                <form method="POST" action="{{ route('admin.cakes.uploads.review', $upload) }}" class="row" style="gap:6px;flex-wrap:wrap" novalidate>
                    @csrf
                    <input class="input" name="note" placeholder="Note (required to reject)" aria-label="Review note" style="min-height:36px;max-width:240px">
                    <button class="btn btn-teal btn-sm" name="decision" value="approved">Approve print</button>
                    <button class="btn btn-outline btn-sm" name="decision" value="rejected">Reject</button>
                </form>
            </div>
        </div>
    @endforeach
</div>
