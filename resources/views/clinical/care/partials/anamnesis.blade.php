<x-card title="Anamnesis">
    @can('anamnesis.manage')
        @if ($writable)
            <form action="{{ route('care.anamnesis.update', $visit) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label" for="anamnesis">Anamnesis</label>
                    <textarea name="anamnesis" id="anamnesis" rows="8" class="form-control @error('anamnesis') is-invalid @enderror">{{ old('anamnesis', $record?->anamnesis) }}</textarea>
                    @error('anamnesis')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <x-button type="submit" icon="bx bx-save">Simpan</x-button>
            </form>
        @endif
    @else
        <p class="mb-0">{{ $record?->anamnesis ?: 'Belum ada anamnesis.' }}</p>
    @endcan
</x-card>
