@props(['image', 'sizes' => '100vw', 'width' => 800, 'eager' => false])
{{-- Approved, licensed real photo only. AVIF / WebP where generated, JPEG fallback; responsive widths. --}}
@php
    $jpeg = collect($image->renditions ?? [])->where('format', 'jpg')->sortBy('width');
    $fallback = $jpeg->first(fn ($rendition) => $rendition['width'] >= $width) ?? $jpeg->last();
@endphp
@if ($image->isApproved() && $fallback)
    <picture class="product-photo">
        @foreach (['avif', 'webp'] as $format)
            @if ($srcset = $image->srcset($format))<source type="image/{{ $format }}" srcset="{{ $srcset }}" sizes="{{ $sizes }}">@endif
        @endforeach
        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($fallback['path']) }}" srcset="{{ $image->srcset('jpg') }}" sizes="{{ $sizes }}"
             alt="{{ $image->localizedAlt() }}" width="{{ $fallback['width'] }}" height="{{ $fallback['height'] }}"
             loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" @if ($eager) fetchpriority="high" @endif>
    </picture>
@endif
