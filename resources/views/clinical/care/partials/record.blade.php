<x-card title="Rekam Medis">
    @can('medical_record.update')
        @if ($writable)
            <form action="{{ route('care.record.update', $visit) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label" for="chief_complaint">Keluhan Utama</label>
                    <textarea name="chief_complaint" id="chief_complaint" rows="3" class="form-control @error('chief_complaint') is-invalid @enderror">{{ old('chief_complaint', $record?->chief_complaint) }}</textarea>
                    @error('chief_complaint')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="clinical_notes">Catatan Klinis</label>
                    <textarea name="clinical_notes" id="clinical_notes" rows="6" class="form-control @error('clinical_notes') is-invalid @enderror">{{ old('clinical_notes', $record?->clinical_notes) }}</textarea>
                    @error('clinical_notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <x-button type="submit" icon="bx bx-save">Simpan</x-button>
            </form>
        @endif
    @else
        <p class="mb-2"><strong>Keluhan utama:</strong> {{ $record?->chief_complaint ?: '-' }}</p>
        <p class="mb-0"><strong>Catatan klinis:</strong> {{ $record?->clinical_notes ?: '-' }}</p>
    @endcan

    @if ($record?->vital_signs)
        <hr>
        <h6 class="text-muted">Ringkasan tanda vital</h6>
        <p class="mb-0">
            TD {{ $record->vital_signs['blood_pressure'] ?? '-' }},
            Nadi {{ $record->vital_signs['pulse'] ?? '-' }},
            Suhu {{ $record->vital_signs['temperature'] ?? '-' }},
            RR {{ $record->vital_signs['respiration'] ?? '-' }}
        </p>
    @endif
</x-card>
