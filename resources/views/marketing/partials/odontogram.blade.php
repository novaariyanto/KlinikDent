@php
    $findings = [
        '16' => ['status' => 'caries', 'surfaces' => ['occlusal' => 'caries'], 'label' => 'Karies', 'note' => 'Permukaan oklusal. Rencana: tumpatan komposit.'],
        '24' => ['status' => 'caries', 'surfaces' => ['mesial' => 'caries'], 'label' => 'Karies', 'note' => 'Permukaan mesial. Dicatat dari kunjungan sebelumnya.'],
        '26' => ['status' => 'filling', 'surfaces' => ['occlusal' => 'filling'], 'label' => 'Tumpatan', 'note' => 'Tumpatan komposit oklusal, 2025.'],
        '36' => ['status' => 'crown', 'surfaces' => [], 'label' => 'Mahkota', 'note' => 'Crown porselen. Kontrol oklusi.'],
        '38' => ['status' => 'impacted', 'surfaces' => [], 'label' => 'Impaksi', 'note' => 'Molar tiga impaksi. Observasi.'],
        '46' => ['status' => 'extracted', 'surfaces' => [], 'label' => 'Dicabut', 'note' => 'Ekstraksi 2024. Tidak perlu diisi ulang.'],
    ];
    $upper = ['18', '17', '16', '15', '14', '13', '12', '11', '21', '22', '23', '24', '25', '26', '27', '28'];
    $lower = ['48', '47', '46', '45', '44', '43', '42', '41', '31', '32', '33', '34', '35', '36', '37', '38'];
    $selected = $selected ?? '16';
    $simple = $simple ?? false;
    $interactive = $interactive ?? true;
@endphp

<div class="odo-chart {{ $simple ? 'odo-chart--simple' : '' }}" @if ($interactive) data-odo-chart @endif>
    <div class="odo-sheet" role="img" aria-label="Odontogram gigi permanen dengan beberapa temuan, termasuk karies pada gigi 16 dan 24.">
        <p class="odo-jaw">Rahang atas</p>
        <div class="odo-quads" aria-hidden="true"><span>Kuadran 1</span><span>Kuadran 2</span></div>
        <div class="odo-row odo-row--upper">
            @foreach ($upper as $i => $number)
                @if ($i === 8)
                    <span class="odo-mid" aria-hidden="true"></span>
                @endif
                @include('marketing.partials.tooth', [
                    'number' => $number,
                    'finding' => $findings[$number] ?? null,
                    'selected' => $number === $selected,
                    'simple' => $simple,
                ])
            @endforeach
        </div>
        <div class="odo-plane" aria-hidden="true"><span>Bidang oklusal</span></div>
        <div class="odo-row odo-row--lower">
            @foreach ($lower as $i => $number)
                @if ($i === 8)
                    <span class="odo-mid" aria-hidden="true"></span>
                @endif
                @include('marketing.partials.tooth', [
                    'number' => $number,
                    'finding' => $findings[$number] ?? null,
                    'selected' => false,
                    'simple' => $simple,
                ])
            @endforeach
        </div>
        <div class="odo-quads odo-quads--bottom" aria-hidden="true"><span>Kuadran 4</span><span>Kuadran 3</span></div>
        <p class="odo-jaw odo-jaw--bottom">Rahang bawah</p>
    </div>

    @if ($interactive)
        <aside class="odo-inspect" data-odo-inspect>
            <p class="odo-inspect__kicker">FDI</p>
            <p class="odo-inspect__title">Gigi <span data-odo-number>{{ $selected }}</span></p>
            <p class="odo-inspect__status" data-odo-label>{{ $findings[$selected]['label'] }}</p>
            <p class="odo-inspect__note" data-odo-note>{{ $findings[$selected]['note'] }}</p>
            <div class="odo-findings" role="group" aria-label="Temuan odontogram">
                @foreach ($findings as $number => $finding)
                    <button
                        type="button"
                        class="odo-finding {{ $number === $selected ? 'is-active' : '' }}"
                        data-odo-focus="{{ $number }}"
                        data-odo-label="{{ $finding['label'] }}"
                        data-odo-note="{{ $finding['note'] }}"
                    >
                        {{ $number }} · {{ $finding['label'] }}
                    </button>
                @endforeach
            </div>
        </aside>
    @endif
</div>
