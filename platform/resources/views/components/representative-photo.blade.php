@props(['image', 'label' => true, 'eager' => false, 'priority' => false, 'sizes' => '(max-width: 640px) 50vw, 260px'])
{{-- Licensed stock photo for a generic product (D-071), always labelled; never shown when an approved photo exists. --}}
<figure class="rep-photo">
    <img src="{{ $image['src'] }}" @if ($image['srcset'])srcset="{{ $image['srcset'] }}" sizes="{{ $sizes }}"@endif alt="{{ $image['alt'] }}" width="800" height="800" loading="{{ $eager || $priority ? 'eager' : 'lazy' }}" decoding="async" @if ($priority) fetchpriority="high" @endif>
    @if ($label)<figcaption class="rep-tag">{{ __('Representative image') }}</figcaption>@endif
</figure>
