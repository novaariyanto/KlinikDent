<x-card title="Catatan Pelayanan">
    @can('care_note.manage')
        @if ($writable)
            <form action="{{ route('care.notes.update', $visit) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label" for="care_notes">Catatan perawat / asisten</label>
                    <textarea name="care_notes" id="care_notes" rows="8" class="form-control @error('care_notes') is-invalid @enderror">{{ old('care_notes', $record?->care_notes) }}</textarea>
                    @error('care_notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <x-button type="submit" icon="bx bx-save">Simpan</x-button>
            </form>
        @endif
    @else
        <p class="mb-0">{{ $record?->care_notes ?: 'Belum ada catatan pelayanan.' }}</p>
    @endcan
</x-card>
