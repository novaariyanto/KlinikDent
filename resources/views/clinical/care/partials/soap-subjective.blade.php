@can('medical_record.update')
    @if ($writable)
        <form action="{{ route('care.record.update', $visit) }}" method="POST" class="soap-form">
            @csrf
            @method('PUT')
            <label class="form-label" for="chief_complaint">Keluhan utama</label>
            <textarea name="chief_complaint" id="chief_complaint" rows="2" class="form-control mb-2">{{ old('chief_complaint', $record?->chief_complaint) }}</textarea>
            <x-button type="submit" variant="light">Simpan</x-button>
        </form>
    @endif
@elsecan('medical_record.view')
    <div class="mb-2">{{ $record?->chief_complaint ?: '—' }}</div>
@endcan

@can('anamnesis.manage')
    @if ($writable)
        <form action="{{ route('care.anamnesis.update', $visit) }}" method="POST" class="soap-form mt-2">
            @csrf
            @method('PUT')
            <label class="form-label" for="anamnesis">Anamnesis / riwayat keluhan</label>
            <textarea name="anamnesis" id="anamnesis" rows="3" class="form-control mb-2">{{ old('anamnesis', $record?->anamnesis) }}</textarea>
            <x-button type="submit" variant="light">Simpan</x-button>
        </form>
    @endif
@elsecan('anamnesis.view')
    <div class="text-muted font-size-12">{{ $record?->anamnesis ?: '' }}</div>
@endcan
