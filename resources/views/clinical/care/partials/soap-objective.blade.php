@can('vital_sign.manage')
    @if ($writable)
        @php($vitals = old() ?: ($record?->vital_signs ?? []))
        <form action="{{ route('care.vitals.update', $visit) }}" method="POST" class="soap-form mb-2">
            @csrf
            @method('PUT')
            <div class="soap-vitals">
                <x-input name="blood_pressure" label="TD" :value="$vitals['blood_pressure'] ?? null" />
                <x-input name="pulse" label="Nadi" :value="$vitals['pulse'] ?? null" />
                <x-input name="temperature" label="Suhu" :value="$vitals['temperature'] ?? null" />
                <x-input name="respiration" label="RR" :value="$vitals['respiration'] ?? null" />
            </div>
            <input type="hidden" name="weight" value="{{ $vitals['weight'] ?? '' }}">
            <input type="hidden" name="height" value="{{ $vitals['height'] ?? '' }}">
            <x-button type="submit" variant="light">Simpan vital</x-button>
        </form>
    @endif
@elseif ($record?->vital_signs)
    <div class="font-size-12 mb-2">TD {{ $record->vital_signs['blood_pressure'] ?? '-' }} · N {{ $record->vital_signs['pulse'] ?? '-' }} · S {{ $record->vital_signs['temperature'] ?? '-' }}</div>
@endcan

@can('examination.manage')
    @if ($writable)
        <form action="{{ route('care.examination.update', $visit) }}" method="POST" class="soap-form">
            @csrf
            @method('PUT')
            <label class="form-label" for="initial_examination">Temuan intraoral / klinis</label>
            <textarea name="initial_examination" id="initial_examination" rows="3" class="form-control mb-2">{{ old('initial_examination', $record?->initial_examination) }}</textarea>
            <x-button type="submit" variant="light">Simpan</x-button>
        </form>
    @endif
@elsecan('examination.view')
    <div>{{ $record?->initial_examination ?: '—' }}</div>
@endcan
