@props([
    'number',
    'finding' => null,
    'selected' => false,
    'simple' => false,
])

@php
    $number = (string) $number;
    $status = $finding['status'] ?? 'healthy';
    $surfaces = $finding['surfaces'] ?? [];
    $digit = (int) substr($number, 1, 1);
    $kind = match (true) {
        $digit <= 2 => 'incisor',
        $digit === 3 => 'canine',
        $digit <= 5 => 'premolar',
        default => 'molar',
    };
    $quadrant = (int) substr($number, 0, 1);
    $isUpper = in_array($quadrant, [1, 2, 5, 6], true);
    $mesialRight = in_array($quadrant, [1, 4, 5, 8], true);
    $inset = match ($kind) {
        'incisor' => 41,
        'canine' => 37,
        'premolar' => 32,
        default => 27,
    };
    $inner = 100 - $inset;
    $topKey = $isUpper ? 'buccal' : 'palatal';
    $bottomKey = $isUpper ? 'palatal' : 'buccal';
    $leftKey = $mesialRight ? 'distal' : 'mesial';
    $rightKey = $mesialRight ? 'mesial' : 'distal';
    $colors = [
        'caries' => '#ef4444',
        'filling' => '#3b82f6',
        'crown' => '#d4a017',
        'extracted' => '#6b7280',
        'impacted' => '#7c3aed',
        'root_canal' => '#a855f7',
    ];
    $whole = in_array($status, ['extracted', 'missing', 'crown', 'impacted', 'root_canal'], true);
    $fill = function (?string $value) use ($status, $whole, $colors): string {
        if ($whole || $status === 'healthy' || ! $value) {
            return '#ffffff';
        }

        return $colors[$value] ?? '#ffffff';
    };
    $paths = [
        $topKey => "M0,0 H100 L{$inner},{$inset} H{$inset} Z",
        $rightKey => "M100,0 V100 L{$inner},{$inner} V{$inset} Z",
        $bottomKey => "M0,100 H100 L{$inner},{$inner} H{$inset} Z",
        $leftKey => "M0,0 V100 L{$inset},{$inner} V{$inset} Z",
        'occlusal' => "M{$inset},{$inset} H{$inner} V{$inner} H{$inset} Z",
    ];
@endphp

<div
    class="odo-tooth odo-tooth--{{ $kind }} {{ $selected ? 'is-selected' : '' }} {{ $status !== 'healthy' ? 'is-finding is-'.$status : '' }}"
    data-odo-tooth="{{ $number }}"
    aria-hidden="true"
>
    @if ($isUpper)
        <span class="odo-num">{{ $number }}</span>
    @endif
    @if ($simple)
        <span class="odo-face"></span>
    @else
        <svg class="odo-svg" viewBox="0 0 100 100" focusable="false">
            @foreach ($paths as $surface => $d)
                <path d="{{ $d }}" fill="{{ $fill($surfaces[$surface] ?? null) }}" stroke="#334155" stroke-width="1.4" vector-effect="non-scaling-stroke"/>
            @endforeach
            <rect x="1.1" y="1.1" width="97.8" height="97.8" fill="none" stroke="#334155" stroke-width="1.35" vector-effect="non-scaling-stroke"/>
            <rect x="{{ $inset }}" y="{{ $inset }}" width="{{ 100 - (2 * $inset) }}" height="{{ 100 - (2 * $inset) }}" fill="none" stroke="#334155" stroke-width="1.35" vector-effect="non-scaling-stroke"/>
            @if ($status === 'extracted')
                <line x1="8" y1="8" x2="92" y2="92" stroke="#111" stroke-width="2.2"/>
                <line x1="92" y1="8" x2="8" y2="92" stroke="#111" stroke-width="2.2"/>
            @endif
            @if ($status === 'crown')
                <rect x="3.5" y="3.5" width="93" height="93" fill="none" stroke="#d4a017" stroke-width="2.6"/>
            @endif
            @if ($status === 'impacted')
                <polygon points="50,14 64,38 36,38" fill="#7c3aed"/>
            @endif
        </svg>
    @endif
    @if (! $isUpper)
        <span class="odo-num">{{ $number }}</span>
    @endif
</div>
