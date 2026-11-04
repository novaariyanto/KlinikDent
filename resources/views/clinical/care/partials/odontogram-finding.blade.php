@php
    $toothConditions = \App\Support\Clinical\ToothConditions::fromRecord($tooth);
@endphp
<article class="odo-finding" data-finding-row="{{ $tooth->tooth_number }}">
    <div class="odo-finding__num">{{ $tooth->tooth_number }}</div>
    <div class="odo-finding__body">
        <div class="odo-finding__conds">
            @foreach ($toothConditions as $condition)
                @php
                    $conditionStatus = \App\Enums\ToothStatus::from($condition['code']);
                    $conditionExtra = \App\Support\Clinical\ToothConditions::describe($condition);
                @endphp
                <div class="odo-finding__status">
                    <i style="background: {{ $conditionStatus->color() }}"></i>
                    <strong>{{ $conditionStatus->label() }}</strong>
                    @if ($conditionExtra !== '')
                        <span>{{ $conditionExtra }}</span>
                    @endif
                    @if ($condition['note'])
                        <em class="odo-finding__cnote">{{ $condition['note'] }}</em>
                    @endif
                </div>
            @endforeach
        </div>
        @if ($tooth->notes)
            <p class="odo-finding__notes">{{ $tooth->notes }}</p>
        @endif
    </div>
    @if ($canManageOdo)
        <div class="odo-finding__actions">
            <button type="button" class="btn btn-sm btn-soft-primary" data-odo-edit="{{ $tooth->tooth_number }}">Ubah</button>
            <button type="button" class="btn btn-sm btn-soft-danger" data-odo-delete="{{ $tooth->tooth_number }}">Hapus</button>
        </div>
    @endif
</article>
