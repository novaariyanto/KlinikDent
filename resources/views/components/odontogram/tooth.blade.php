@props([
    'number',
    'record' => null,
])

@php
    $number = (string) $number;
    $conditions = \App\Support\Clinical\ToothConditions::fromRecord($record);
    $status = \App\Support\Clinical\ToothConditions::primary($conditions);
    $codes = array_column($conditions, 'code');
    $surfaceMap = \App\Support\Clinical\ToothConditions::surfaceMap($conditions);
    $badges = \App\Support\Clinical\ToothConditions::badges($conditions);
    $kind = \App\Support\Clinical\FdiTeeth::kind($number);
    $isUpper = \App\Support\Clinical\FdiTeeth::isUpper($number);
    $isPrimary = \App\Support\Clinical\FdiTeeth::isPrimary($number);
    $isAnterior = \App\Support\Clinical\FdiTeeth::isAnterior($number);
    $mesialRight = \App\Support\Clinical\FdiTeeth::mesialOnRight($number);
    $has = fn (string $code) => in_array($code, $codes, true);

    $inset = match ($kind) {
        'incisor' => 41,
        'canine' => 37,
        'premolar' => 32,
        default => 27,
    };
    $outer = 100;
    $inner = $outer - $inset;

    $innerKey = $isUpper ? 'palatal' : 'lingual';
    $topKey = $isUpper ? 'buccal' : $innerKey;
    $bottomKey = $isUpper ? $innerKey : 'buccal';
    $leftKey = $mesialRight ? 'distal' : 'mesial';
    $rightKey = $mesialRight ? 'mesial' : 'distal';
    $centerKey = $isAnterior ? 'incisal' : 'occlusal';

    $paths = [
        $topKey => "M0,0 H{$outer} L{$inner},{$inset} H{$inset} Z",
        $rightKey => "M{$outer},0 V{$outer} L{$inner},{$inner} V{$inset} Z",
        $bottomKey => "M0,{$outer} H{$outer} L{$inner},{$inner} H{$inset} Z",
        $leftKey => "M0,0 V{$outer} L{$inset},{$inner} V{$inset} Z",
        $centerKey => "M{$inset},{$inset} H{$inner} V{$inner} H{$inset} Z",
    ];

    $fillFor = fn (?string $code): string => \App\Enums\ToothStatus::tryFrom((string) $code)?->color() ?? '#ffffff';
    $title = $number.' — '.\App\Support\Clinical\ToothConditions::summary($conditions);
@endphp

<div
    {{ $attributes->class(array_merge([
        'odo-tooth',
        'odo-tooth--'.$kind,
        'is-primary' => $isPrimary,
        'is-upper' => $isUpper,
        'is-lower' => ! $isUpper,
        'is-multi' => count($conditions) > 1,
    ], array_map(fn ($code) => 'is-'.$code, $codes))) }}
    data-number="{{ $number }}"
    data-kind="{{ $kind }}"
    data-status="{{ $status->value }}"
    data-notes="{{ $record?->notes }}"
    data-surfaces='@json((object) $surfaceMap)'
    data-conditions='@json($conditions)'
    title="{{ $title }}"
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
                style="--surf: {{ $fillFor($surfaceMap[$surface] ?? null) }}"
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

        @if ($has('extracted') || $has('missing'))
            <g class="odo-overlay odo-overlay--x {{ ! $has('extracted') ? 'is-missing' : '' }}">
                <line x1="7" y1="7" x2="93" y2="93" />
                <line x1="93" y1="7" x2="7" y2="93" />
            </g>
        @endif

        @if ($has('crown'))
            <rect class="odo-overlay odo-overlay--crown" x="3.5" y="3.5" width="93" height="93" />
            <rect class="odo-overlay odo-overlay--crown-inner" x="7" y="7" width="86" height="86" />
        @endif

        @if ($has('root_canal'))
            <polygon class="odo-overlay odo-overlay--rct" points="50,38 61,62 39,62" />
        @endif

        @if ($has('fracture'))
            <polyline class="odo-overlay odo-overlay--fracture" points="22,8 38,32 28,52 46,72 40,92" />
        @endif

        @if ($has('implant'))
            <circle class="odo-overlay odo-overlay--implant" cx="50" cy="50" r="11" />
        @endif

        @if ($has('bridge'))
            <line class="odo-overlay odo-overlay--bridge" x1="4" y1="10" x2="96" y2="10" />
            <line class="odo-overlay odo-overlay--bridge" x1="4" y1="90" x2="96" y2="90" />
        @endif

        @if ($has('impacted'))
            <polygon class="odo-overlay odo-overlay--impacted" points="50,14 64,38 36,38" />
        @endif

        @foreach (array_slice($badges, 0, 2) as $i => $badge)
            <text
                class="odo-overlay {{ $badge['code'] === 'mobility' ? 'odo-overlay--mobility' : 'odo-overlay--code' }} {{ count($badges) > 1 ? 'is-stacked' : '' }}"
                x="50"
                y="{{ count($badges) > 1 ? ($i === 0 ? 44 : 74) : 58 }}"
                style="fill: {{ $badge['color'] }}"
            >{{ $badge['text'] }}</text>
        @endforeach

        @if (count($badges) > 2)
            <text class="odo-overlay odo-overlay--more" x="94" y="16">+{{ count($badges) - 2 }}</text>
        @endif
    </svg>

    @if (! $isUpper)
        <div class="odo-num">{{ $number }}</div>
    @endif
</div>
