@php
    $records = collect($state)->mapWithKeys(fn ($items, $number) => [(string) $number => $timeline->record((string) $number, $items)])->all();
    $rows = [
        ['key' => 'upper_primary', 'class' => 'odo-row--upper odo-row--primary', 'pad' => true],
        ['key' => 'upper_adult', 'class' => 'odo-row--upper', 'pad' => false],
        ['key' => 'lower_adult', 'class' => 'odo-row--lower', 'pad' => false],
        ['key' => 'lower_primary', 'class' => 'odo-row--lower odo-row--primary', 'pad' => true],
    ];
@endphp
<div class="odo-chart odo-chart--static" aria-label="Odontogram baca-saja">
    <div class="odo-sheet">
        <div class="odo-jaw-name">Rahang atas</div>
        @foreach ($rows as $row)
            @if ($row['key'] === 'lower_adult')
                <div class="odo-plane"><span>Bidang oklusal</span></div>
            @endif
            <div class="odo-row {{ $row['class'] }}">
                @if ($row['pad'])
                    @for ($i = 0; $i < 3; $i++)<span class="odo-skip" aria-hidden="true"></span>@endfor
                @endif
                @foreach ($chart[$row['key']][0] as $number)
                    <x-odontogram.tooth :number="$number" :record="$records[$number] ?? null" />
                @endforeach
                <span class="odo-midline"></span>
                @foreach ($chart[$row['key']][1] as $number)
                    <x-odontogram.tooth :number="$number" :record="$records[$number] ?? null" />
                @endforeach
                @if ($row['pad'])
                    @for ($i = 0; $i < 3; $i++)<span class="odo-skip" aria-hidden="true"></span>@endfor
                @endif
            </div>
        @endforeach
        <div class="odo-jaw-name">Rahang bawah</div>
    </div>
</div>
