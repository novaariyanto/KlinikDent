@props([
    'type' => 'button',
    'variant' => 'primary',
    'href' => null,
    'icon' => null,
])

@php
    $classes = ['btn', 'btn-'.$variant, 'waves-effect', 'waves-light'];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <i class="{{ $icon }} me-1"></i>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($icon)
            <i class="{{ $icon }} me-1"></i>
        @endif
        {{ $slot }}
    </button>
@endif
