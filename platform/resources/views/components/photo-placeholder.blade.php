@props(['icon' => 'photo', 'label' => 'Photo pending', 'class' => ''])
{{-- Real-images-only policy: neutral development placeholder, never a fake product photo. --}}
<div class="ph {{ $class }}" role="img" aria-label="{{ $label }}">
    <x-icon :name="$icon" />
    <span class="ph-tag">{{ $label }}</span>
</div>
