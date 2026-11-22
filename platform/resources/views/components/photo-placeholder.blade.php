@props(['icon' => 'photo', 'label' => null, 'class' => ''])
@php $label ??= __('Photo pending'); @endphp
{{-- Neutral "photo pending" placeholder until an approved image exists; never a fake product photo. --}}
<div class="ph {{ $class }}" role="img" aria-label="{{ $label }}">
    <x-icon :name="$icon" />
    <span class="ph-tag">{{ $label }}</span>
</div>
