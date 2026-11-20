@extends('layouts.admin', ['title' => 'Photo review'])

@section('admin')
    <div class="admin-top"><h1>Photo review</h1></div>
    <div class="row" style="margin-bottom:12px;gap:6px">
        @foreach (['pending' => 'Waiting for review', 'approved' => 'Published', 'rejected' => 'Rejected', 'withdrawn' => 'Withdrawn'] as $value => $label)
            <a class="fchip" href="{{ route('admin.media.queue', ['status' => $value]) }}" @if ($status === $value) style="background:var(--bh-navy);color:#fff" @endif>{{ $label }} ({{ $counts[$value] ?? 0 }})</a>
        @endforeach
    </div>

    @if ($images->isEmpty())
        <div class="card empty-state"><x-icon name="photo" /><p><strong>Nothing here</strong></p></div>
    @else
        <div class="media-grid">
            @foreach ($images as $image)
                <article class="card media-card">
                    <a href="{{ route('admin.media.preview', $image) }}" target="_blank" rel="noopener"><img src="{{ route('admin.media.preview', $image) }}" alt="{{ $image->alt_text }}" loading="lazy" width="320" height="240"></a>
                    <div class="card-pad small">
                        <strong>{{ $image->product->name }}</strong>@if ($image->variant) <span class="muted">({{ $image->variant->sku }})</span>@endif
                        <table class="specs" style="margin:6px 0"><tbody>
                            <tr><th>Alt text</th><td>{{ $image->alt_text }}@if ($image->alt_text_bn)<br><span lang="bn">{{ $image->alt_text_bn }}</span>@endif</td></tr>
                            <tr><th>Source</th><td>{{ $image->source_type?->label() ?? '—' }}</td></tr>
                            <tr><th>Rights holder</th><td>{{ $image->creator ?? '—' }}</td></tr>
                            <tr><th>Licence</th><td>{{ $image->license_type ?? '—' }}{{ $image->license_reference ? ' · '.$image->license_reference : '' }}{{ $image->license_expires_at ? ' · until '.$image->license_expires_at->format('d M Y') : '' }}</td></tr>
                            @if ($image->source_url)<tr><th>Source URL</th><td style="word-break:break-all">{{ $image->source_url }}</td></tr>@endif
                            <tr><th>Size</th><td>{{ $image->width }}×{{ $image->height }} px</td></tr>
                            <tr><th>Uploaded by</th><td>{{ $image->uploader?->name ?? '—' }}</td></tr>
                            <tr><th>Attested real</th><td>{{ $image->authenticity_attested ? 'Yes' : 'No' }}</td></tr>
                            @if ($image->review_note)<tr><th>Note</th><td>{{ $image->review_note }}</td></tr>@endif
                        </tbody></table>
                        @if ($image->review_status === 'pending')
                            @php $missing = $image->missingForApproval(); $own = $image->uploaded_by === auth()->id() && config('media.require_second_reviewer'); @endphp
                            @if ($missing)<p class="field-error">Missing: {{ implode(', ', $missing) }}</p>@endif
                            @if ($own)<p class="hint">You uploaded this photo, so another staff member must approve it.</p>@endif
                            <p class="hint">Check: it shows this exact product and pack size, it is a real photo (no AI generation, no fake backgrounds), and the licence covers website use.</p>
                            <div class="row" style="gap:6px;align-items:flex-end">
                                @if (! $missing && ! $own)
                                    <form method="POST" action="{{ route('admin.media.approve', $image) }}">@csrf<button class="btn btn-primary btn-sm" type="submit">Approve &amp; publish</button></form>
                                @endif
                                <form method="POST" action="{{ route('admin.media.reject', $image) }}" class="row" style="gap:6px;align-items:flex-end" novalidate>@csrf
                                    <label class="sr-only" for="reason-{{ $image->id }}">Reason</label>
                                    <input class="input" id="reason-{{ $image->id }}" name="reason" placeholder="Rejection reason" required style="min-height:34px">
                                    <button class="btn btn-outline btn-sm" type="submit">Reject</button></form>
                            </div>
                        @elseif ($image->isApproved())
                            <form method="POST" action="{{ route('admin.media.withdraw', $image) }}" class="row" style="gap:6px;align-items:flex-end" novalidate>@csrf
                                <label class="sr-only" for="withdraw-{{ $image->id }}">Reason</label>
                                <input class="input" id="withdraw-{{ $image->id }}" name="reason" placeholder="Reason to withdraw" required style="min-height:34px">
                                <button class="btn btn-outline btn-sm" type="submit">Withdraw</button></form>
                        @endif
                        @can('media.manage')<a class="small" href="{{ route('admin.media.product', $image->product) }}">Product gallery →</a>@endcan
                    </div>
                </article>
            @endforeach
        </div>
        <div style="margin-top:12px">{{ $images->links() }}</div>
    @endif
@endsection
