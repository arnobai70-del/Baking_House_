@props(['icon' => 'photo', 'label' => null, 'class' => ''])
@php $label ??= __('Photo pending'); @endphp
{{-- Real-images-only policy: neutral development placeholder, never a fake product photo. --}}
<div class="ph {{ $class }}" role="img" aria-label="{{ $label }}">
    <x-icon :name="$icon" />
    <span class="ph-tag">{{ $label }}</span>
</div>
