<x-card title="Diagnosis">
    <x-table>
        <thead>
            <tr>
                <th>Kode</th>
                <th>Deskripsi</th>
                <th>Gigi</th>
                @can('diagnosis.manage')<th></th>@endcan
            </tr>
        </thead>
        <tbody>
            @forelse ($visit->diagnoses as $diagnosis)
                <tr>
                    <td>{{ $diagnosis->code }}</td>
                    <td>{{ $diagnosis->description }}</td>
                    <td>{{ $diagnosis->tooth_number ?: '-' }}</td>
                    @can('diagnosis.manage')
                        <td class="text-end">
                            @if ($writable)
                                <form action="{{ route('care.diagnosis.destroy', [$visit, $diagnosis]) }}" method="POST" onsubmit="return confirm('Hapus diagnosis ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-soft-danger" type="submit">Hapus</button>
                                </form>
                            @endif
                        </td>
                    @endcan
                </tr>
            @empty
                <tr><td colspan="4" class="text-muted">Belum ada diagnosis.</td></tr>
            @endforelse
        </tbody>
    </x-table>

    @can('diagnosis.manage')
        @if ($writable)
            <form action="{{ route('care.diagnosis.store', $visit) }}" method="POST" class="mt-3">
                @csrf
                <div class="row">
                    <div class="col-md-3">
                        <x-input name="code" label="Kode" :value="old('code')" required />
                    </div>
                    <div class="col-md-6">
                        <x-input name="description" label="Deskripsi" :value="old('description')" required />
                    </div>
                    <div class="col-md-3">
                        <x-input name="tooth_number" label="Gigi (opsional)" :value="old('tooth_number')" />
                    </div>
                </div>
                <x-button type="submit" icon="bx bx-plus">Tambah Diagnosis</x-button>
            </form>
        @endif
    @endcan
</x-card>
