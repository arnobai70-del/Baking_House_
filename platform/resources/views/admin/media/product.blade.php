@extends('layouts.admin', ['title' => 'Photos · '.$product->name])

@section('admin')
    <div class="admin-top">
        <h1>Photos — {{ $product->name }}</h1><span class="spacer"></span>
        @can('media.approve')<a class="btn btn-outline btn-sm" href="{{ route('admin.media.queue') }}">Review queue</a>@endcan
        <a class="btn btn-ghost btn-sm" href="{{ route('admin.products.edit', $product) }}">← Product</a>
    </div>
    <div class="alert alert-info" style="margin-bottom:16px"><x-icon name="info" /><span>Real images only: authentic photos of this exact product or licensed real photographs. AI-generated or AI-altered images are not allowed. Uploads stay private until a <strong>different</strong> staff member approves them; only approved photos get public files.</span></div>

    <div class="card" style="margin-bottom:16px">
        <div class="panel-title"><h2>Gallery ({{ $product->images->count() }})</h2></div>
        @if ($product->images->isEmpty())
            <div class="empty-state"><x-icon name="photo" /><p><strong>No photos yet</strong></p><p class="muted small">The storefront shows the labelled placeholder until a photo is approved.</p></div>
        @else
            <div class="media-grid">
                @foreach ($product->images as $image)
                    <article class="card media-card">
                        <img src="{{ route('admin.media.preview', $image) }}" alt="{{ $image->alt_text }}" loading="lazy" width="320" height="240">
                        <div class="card-pad">
                            <div class="row" style="justify-content:space-between;gap:6px">
                                <span class="status {{ ['approved' => 'ok', 'pending' => 'warn', 'rejected' => '', 'withdrawn' => ''][$image->review_status] ?? '' }}">{{ ucfirst($image->review_status) }}</span>
                                @if ($image->is_primary)<span class="status info">Main photo</span>@endif
                            </div>
                            <p class="small" style="margin:6px 0">{{ $image->alt_text }}</p>
                            <p class="muted small" style="margin:0">{{ $image->width }}×{{ $image->height }} · {{ $image->source_type?->label() ?? 'source missing' }} · {{ $image->creator }}<br>Uploaded by {{ $image->uploader?->name ?? '—' }}@if ($image->reviewer) · reviewed by {{ $image->reviewer->name }}@endif</p>
                            @if ($image->review_note)<p class="small" style="margin:6px 0 0"><strong>Review note:</strong> {{ $image->review_note }}</p>@endif
                            @if ($image->review_status === 'pending' && $image->missingForApproval())
                                <p class="field-error" style="margin:6px 0 0">Missing before approval: {{ implode(', ', $image->missingForApproval()) }}</p>
                            @endif
                            <div class="row" style="gap:4px;margin-top:8px">
                                <form method="POST" action="{{ route('admin.media.move', $image) }}">@csrf<input type="hidden" name="direction" value="up"><button class="btn btn-ghost btn-sm" type="submit" aria-label="Move earlier">↑</button></form>
                                <form method="POST" action="{{ route('admin.media.move', $image) }}">@csrf<input type="hidden" name="direction" value="down"><button class="btn btn-ghost btn-sm" type="submit" aria-label="Move later">↓</button></form>
                                @if ($image->isApproved() && ! $image->is_primary)
                                    <form method="POST" action="{{ route('admin.media.primary', $image) }}">@csrf<button class="btn btn-outline btn-sm" type="submit">Make main photo</button></form>
                                @endif
                                @unless ($image->isApproved())
                                    <form method="POST" action="{{ route('admin.media.destroy', $image) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" type="submit">Delete</button></form>
                                @endunless
                            </div>
                            <details style="margin-top:8px"><summary class="small" style="cursor:pointer">Edit details</summary>
                                <form method="POST" action="{{ route('admin.media.update', $image) }}" style="margin-top:8px" novalidate>
                                    @csrf @method('PUT')
                                    @include('admin.media._details', ['image' => $image])
                                    <p class="hint">Changing source or licence details of a published photo takes it offline until it is approved again.</p>
                                    <button class="btn btn-teal btn-sm" type="submit">Save details</button>
                                </form>
                            </details>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.media.store', $product) }}" enctype="multipart/form-data" class="card card-pad" novalidate>
        @csrf
        <h2 style="font-size:var(--bh-fs-lg)">Upload a photo</h2>
        <div class="field"><label for="photo">Photo file (JPEG, PNG or WebP; at least 600×600 px; max 10 MB)</label>
            <input class="input" id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required>
            @error('photo')<p class="field-error">{{ $message }}</p>@enderror</div>
        @include('admin.media._details', ['image' => null])
        <p class="hint">The file is re-encoded on upload: camera metadata (including GPS location) is removed. Files whose metadata declares AI generation are refused.</p>
        <button class="btn btn-primary" type="submit"><x-icon name="photo" /> Upload for review</button>
    </form>
@endsection
