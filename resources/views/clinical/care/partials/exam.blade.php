<x-card title="Pemeriksaan Awal">
    @can('examination.manage')
        @if ($writable)
            <form action="{{ route('care.examination.update', $visit) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label" for="initial_examination">Temuan pemeriksaan awal</label>
                    <textarea name="initial_examination" id="initial_examination" rows="8" class="form-control @error('initial_examination') is-invalid @enderror">{{ old('initial_examination', $record?->initial_examination) }}</textarea>
                    @error('initial_examination')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                </div>
                <x-button type="submit" icon="bx bx-save">Simpan</x-button>
            </form>
        @endif
    @else
        <p class="mb-0">{{ $record?->initial_examination ?: 'Belum ada pemeriksaan awal.' }}</p>
    @endcan
</x-card>
