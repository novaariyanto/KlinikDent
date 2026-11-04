<div class="odo-legend">
    <div class="odo-legend__title">Kode simbol</div>
    @foreach (\App\Enums\ToothStatus::groups() as $groupKey => $groupLabel)
        <div class="odo-legend__group">{{ $groupLabel }}</div>
        <div class="odo-legend__grid">
            @foreach ($toothStatusMeta as $value => $meta)
                @continue($meta['group'] !== $groupKey)
                <div class="odo-legend__item">
                    <i style="background: {{ $meta['color'] }}">{{ $meta['symbol'] }}</i>
                    <span>{{ $meta['label'] }}</span>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
