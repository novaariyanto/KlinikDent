<section class="care-section" id="resep">
    <div class="care-section__head">
        <h5 class="care-section__title">Resep</h5>
        <p class="care-section__hint">Isi hanya jika ada obat. Draft dapat diubah atau dihapus; stok belum dipotong saat dikirim ke farmasi.</p>
    </div>
    <div class="care-section__body">
        @forelse ($visit->prescriptions as $prescription)
            <div class="plan-block">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Resep #{{ $prescription->id }}</strong>
                    <span class="{{ $prescription->status->badgeClass() }}">{{ $prescription->status->label() }}</span>
                </div>
                <x-table>
                    <thead>
                        <tr>
                            <th>Obat</th>
                            <th>Dosis</th>
                            <th>Frekuensi</th>
                            <th>Aturan pakai</th>
                            <th>Qty</th>
                            @if ($writable && $prescription->isDraft() && auth()->user()?->can('prescription.update'))
                                <th></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($prescription->items as $item)
                            <tr>
                                <td>{{ $item->medicine?->name }}</td>
                                <td>{{ $item->dosage ?: '-' }}</td>
                                <td>{{ $item->frequency ?: '-' }}</td>
                                <td>{{ $item->duration ?: '-' }}</td>
                                <td>{{ $item->quantity }}</td>
                                @if ($writable && $prescription->isDraft() && auth()->user()?->can('prescription.update'))
                                    <td class="text-end text-nowrap">
                                        <button type="button" class="btn btn-sm btn-soft-primary" data-toggle-panel="#rx-edit-{{ $item->id }}">Ubah</button>
                                        <form action="{{ route('care.prescription-item.destroy', [$visit, $item]) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus obat ini dari resep?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-soft-danger" type="submit">Hapus</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                            @if ($writable && $prescription->isDraft() && auth()->user()?->can('prescription.update'))
                                <tr id="rx-edit-{{ $item->id }}" hidden>
                                    <td colspan="6">
                                        <form action="{{ route('care.prescription-item.update', [$visit, $item]) }}" method="POST" class="care-inline-form">
                                            @csrf
                                            @method('PUT')
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <x-input name="dosage" :id="'rx-dosage-'.$item->id" label="Dosis" :value="old('dosage', $item->dosage)" />
                                                </div>
                                                <div class="col-md-3">
                                                    <x-input name="frequency" :id="'rx-frequency-'.$item->id" label="Frekuensi" :value="old('frequency', $item->frequency)" />
                                                </div>
                                                <div class="col-md-3">
                                                    <x-input name="duration" :id="'rx-duration-'.$item->id" label="Aturan pakai" :value="old('duration', $item->duration)" />
                                                </div>
                                                <div class="col-md-3">
                                                    <x-input name="quantity" type="number" :id="'rx-qty-'.$item->id" label="Qty" :value="old('quantity', $item->quantity)" required />
                                                </div>
                                            </div>
                                            <x-button type="submit" icon="bx bx-save">Simpan</x-button>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </x-table>
                @if ($writable && $prescription->isDraft() && auth()->user()?->can('prescription.update'))
                    <form action="{{ route('care.prescription.send', [$visit, $prescription]) }}" method="POST" class="mt-2" onsubmit="return confirm('Kirim resep ke farmasi? Stok belum dipotong.')">
                        @csrf
                        <x-button type="submit" variant="info" icon="bx bx-send">Kirim ke Farmasi</x-button>
                    </form>
                @endif
            </div>
        @empty
            <p class="text-muted">Tidak ada resep.</p>
        @endforelse

        @can('prescription.create')
            @if ($writable)
                <button type="button" class="btn btn-soft-primary mb-3" data-toggle-panel="#rx-form">
                    Tambah obat
                </button>
                <form action="{{ route('care.prescription-item.store', $visit) }}" method="POST" id="rx-form" @if (! old('medicine_id')) hidden @endif>
                    @csrf
                    <div class="care-typeahead mb-3" data-typeahead data-typeahead-items="{{ json_encode($medicines->map(fn ($medicine) => ['id' => $medicine->id, 'label' => $medicine->name.' ('.$medicine->unitLabel().')'])->values()) }}">
                        <label class="form-label">Cari obat</label>
                        <input type="search" class="form-control" data-typeahead-q placeholder="Nama obat" autocomplete="off">
                        <input type="hidden" name="medicine_id" data-typeahead-id value="{{ old('medicine_id') }}" required>
                        <div class="care-typeahead__list" data-typeahead-results hidden></div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <x-input name="dosage" label="Dosis" :value="old('dosage')" />
                        </div>
                        <div class="col-md-3">
                            <x-input name="frequency" label="Frekuensi" :value="old('frequency')" />
                        </div>
                        <div class="col-md-3">
                            <x-input name="duration" label="Aturan pakai" :value="old('duration')" placeholder="3x1 sesudah makan" />
                        </div>
                        <div class="col-md-3">
                            <x-input name="quantity" type="number" label="Qty" :value="old('quantity', 1)" required />
                        </div>
                    </div>
                    <x-button type="submit" icon="bx bx-plus">Tambah ke Draft</x-button>
                </form>
            @endif
        @endcan
    </div>
</section>
