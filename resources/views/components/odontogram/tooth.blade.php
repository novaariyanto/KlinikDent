@props([
    'number',
    'record' => null,
])

@php
    $number = (string) $number;
    $status = $record?->status ?? \App\Enums\ToothStatus::Healthy;
    $surfaces = $record?->surfaces ?? [];
    $kind = \App\Support\Clinical\FdiTeeth::kind($number);
    $isUpper = \App\Support\Clinical\FdiTeeth::isUpper($number);
    $isPrimary = \App\Support\Clinical\FdiTeeth::isPrimary($number);
    $mesialRight = \App\Support\Clinical\FdiTeeth::mesialOnRight($number);
    $showX = in_array($status, [\App\Enums\ToothStatus::Extracted, \App\Enums\ToothStatus::Missing], true);

    $inset = match ($kind) {
        'incisor' => 41,
        'canine' => 37,
        'premolar' => 32,
        default => 27,
    };
    $outer = 100;
    $inner = $outer - $inset;

    $topKey = $isUpper ? 'buccal' : 'palatal';
    $bottomKey = $isUpper ? 'palatal' : 'buccal';
    $leftKey = $mesialRight ? 'distal' : 'mesial';
    $rightKey = $mesialRight ? 'mesial' : 'distal';

    $paths = [
        $topKey => "M0,0 H{$outer} L{$inner},{$inset} H{$inset} Z",
        $rightKey => "M{$outer},0 V{$outer} L{$inner},{$inner} V{$inset} Z",
        $bottomKey => "M0,{$outer} H{$outer} L{$inner},{$inner} H{$inset} Z",
        $leftKey => "M0,0 V{$outer} L{$inset},{$inner} V{$inset} Z",
        'occlusal' => "M{$inset},{$inset} H{$inner} V{$inner} H{$inset} Z",
    ];

    $fillFor = function (?string $value) use ($status): string {
        if ($status->isWholeTooth() || $status === \App\Enums\ToothStatus::Healthy) {
            return '#ffffff';
        }

        if (! $value) {
            return '#ffffff';
        }

        $surfaceStatus = \App\Enums\ToothStatus::tryFrom($value);

        if (! $surfaceStatus || $surfaceStatus === \App\Enums\ToothStatus::Healthy) {
            return '#ffffff';
        }

        return $surfaceStatus->color();
    };
@endphp

<div
    {{ $attributes->class([
        'odo-tooth',
        'odo-tooth--'.$kind,
        'is-primary' => $isPrimary,
        'is-upper' => $isUpper,
        'is-lower' => ! $isUpper,
        'is-'.$status->value => $status !== \App\Enums\ToothStatus::Healthy,
    ]) }}
    data-number="{{ $number }}"
    data-status="{{ $status->value }}"
    data-notes="{{ $record?->notes }}"
    data-surfaces='@json($surfaces)'
    title="{{ $number }} — {{ $status->label() }}"
>
    @if ($isUpper)
        <div class="odo-num">{{ $number }}</div>
    @endif

    <svg class="odo-svg" viewBox="0 0 100 100" focusable="false" aria-hidden="true">
        @foreach ($paths as $surface => $d)
            <path
                class="odo-surf"
                data-surface="{{ $surface }}"
                data-label="{{ \App\Support\Clinical\FdiTeeth::surfaceHoverLabel($number, $surface) }}"
                d="{{ $d }}"
                style="--surf: {{ $fillFor($surfaces[$surface] ?? null) }}"
            >
                <title>{{ \App\Support\Clinical\FdiTeeth::surfaceHoverLabel($number, $surface) }}</title>
            </path>
        @endforeach

        <g class="odo-strokes">
            <rect x="1.1" y="1.1" width="97.8" height="97.8" />
            <rect x="{{ $inset }}" y="{{ $inset }}" width="{{ $outer - (2 * $inset) }}" height="{{ $outer - (2 * $inset) }}" />
            <line x1="0" y1="0" x2="{{ $inset }}" y2="{{ $inset }}" />
            <line x1="100" y1="0" x2="{{ $inner }}" y2="{{ $inset }}" />
            <line x1="0" y1="100" x2="{{ $inset }}" y2="{{ $inner }}" />
            <line x1="100" y1="100" x2="{{ $inner }}" y2="{{ $inner }}" />
        </g>

        @if ($showX)
            <g class="odo-overlay odo-overlay--x {{ $status === \App\Enums\ToothStatus::Missing ? 'is-missing' : '' }}">
                <line x1="7" y1="7" x2="93" y2="93" />
                <line x1="93" y1="7" x2="7" y2="93" />
            </g>
        @endif

        @if ($status === \App\Enums\ToothStatus::Crown)
            <rect class="odo-overlay odo-overlay--crown" x="3.5" y="3.5" width="93" height="93" />
            <rect class="odo-overlay odo-overlay--crown-inner" x="7" y="7" width="86" height="86" />
        @endif

        @if ($status === \App\Enums\ToothStatus::RootCanal)
            <polygon class="odo-overlay odo-overlay--rct" points="50,38 61,62 39,62" />
        @endif

        @if ($status === \App\Enums\ToothStatus::Fracture)
            <polyline class="odo-overlay odo-overlay--fracture" points="22,8 38,32 28,52 46,72 40,92" />
        @endif

        @if ($status === \App\Enums\ToothStatus::Implant)
            <circle class="odo-overlay odo-overlay--implant" cx="50" cy="50" r="11" />
        @endif

        @if ($status === \App\Enums\ToothStatus::Bridge)
            <line class="odo-overlay odo-overlay--bridge" x1="4" y1="10" x2="96" y2="10" />
            <line class="odo-overlay odo-overlay--bridge" x1="4" y1="90" x2="96" y2="90" />
        @endif

        @if ($status === \App\Enums\ToothStatus::Impacted)
            <polygon class="odo-overlay odo-overlay--impacted" points="50,14 64,38 36,38" />
        @endif

        @if ($status === \App\Enums\ToothStatus::Mobility)
            <text class="odo-overlay odo-overlay--mobility" x="50" y="56">M</text>
        @endif
    </svg>

    @if (! $isUpper)
        <div class="odo-num">{{ $number }}</div>
    @endif
</div>
