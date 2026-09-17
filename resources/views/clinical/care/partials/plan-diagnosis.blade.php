<section class="care-section" id="diagnosis">
    <div class="care-section__head">
        <h5 class="care-section__title">Diagnosis</h5>
        <p class="care-section__hint">Cari ICD, lalu tambahkan. Gigi opsional.</p>
    </div>
    <div class="care-section__body">
        <x-table>
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Diagnosa</th>
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
                    <div class="care-typeahead mb-3" data-typeahead data-typeahead-url="{{ route('care.diagnosis.search', $visit) }}">
                        <label class="form-label">Cari ICD-10</label>
                        <input type="search" class="form-control" data-typeahead-q placeholder="Ketik kode atau nama, mis. karies" autocomplete="off">
                        <div class="care-typeahead__list" data-typeahead-results hidden></div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <x-input name="code" label="Kode" :value="old('code')" required />
                        </div>
                        <div class="col-md-6">
                            <x-input name="description" label="Diagnosa" :value="old('description')" required />
                        </div>
                        <div class="col-md-3">
                            <x-input name="tooth_number" label="Gigi (opsional)" :value="old('tooth_number')" />
                        </div>
                    </div>
                    <x-button type="submit" icon="bx bx-plus">Tambah Diagnosis</x-button>
                </form>
            @endif
        @endcan
    </div>
</section>
