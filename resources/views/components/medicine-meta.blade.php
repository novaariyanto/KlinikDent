@props([
    'medicine' => null,
    'name' => null,
    'showMissingKfa' => false,
    'compact' => false,
])

@php
    $resolvedName = $name ?? $medicine?->name;
    $kfa = $medicine?->kfa_code;
    $strength = $medicine?->strengthLabel();
    $font = $compact ? 'font-size-12' : 'font-size-13';
@endphp

@if (filled($resolvedName))
    <div>{{ $resolvedName }}</div>
@endif
@if (filled($kfa))
    <div class="text-muted {{ $font }}">KFA {{ $kfa }}</div>
@elseif ($showMissingKfa && $medicine)
    <div class="text-warning {{ $font }}">Belum ada kode KFA</div>
@endif
@if (filled($strength))
    <div class="text-muted {{ $font }}">Kekuatan {{ $strength }}</div>
@endif
