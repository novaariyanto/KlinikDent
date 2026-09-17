@can('diagnosis.view')
    <ul class="soap-list">
        @forelse ($visit->diagnoses as $diagnosis)
            <li>
                <strong>{{ $diagnosis->code }}</strong> {{ $diagnosis->description }}
                @if ($diagnosis->tooth_number)<span class="text-muted">· {{ $diagnosis->tooth_number }}</span>@endif
                @can('diagnosis.manage')
                    @if ($writable)
                        <form action="{{ route('care.diagnosis.destroy', [$visit, $diagnosis]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus diagnosis ini?')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-link btn-sm p-0" type="submit">hapus</button>
                        </form>
                    @endif
                @endcan
            </li>
        @empty
            <li class="text-muted">Belum ada diagnosa</li>
        @endforelse
    </ul>
    @can('diagnosis.manage')
        @if ($writable)
            <form action="{{ route('care.diagnosis.store', $visit) }}" method="POST" class="soap-form">
                @csrf
                <x-input name="code" label="ICD / kode" :value="old('code')" required />
                <x-input name="description" label="Diagnosa kerja" :value="old('description')" required />
                <x-input name="tooth_number" label="Gigi" :value="old('tooth_number')" />
                <x-button type="submit" variant="light" icon="bx bx-plus">Tambah</x-button>
            </form>
        @endif
    @endcan
@else
    <span class="text-muted">—</span>
@endcan
