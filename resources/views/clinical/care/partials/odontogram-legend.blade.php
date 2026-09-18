<div class="odo-legend">
    <div class="odo-legend__title">Kode simbol</div>
    @foreach ($toothStatusMeta as $value => $meta)
        <div class="odo-legend__item">
            <i style="background: {{ $meta['color'] }}">{{ $meta['symbol'] }}</i>
            <span>{{ $meta['label'] }}</span>
        </div>
    @endforeach
</div>
