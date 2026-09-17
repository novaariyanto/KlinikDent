<x-card title="Resep">
    @foreach ($visit->prescriptions as $prescription)
        <div class="border rounded p-3 mb-3">
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
                        <th>Durasi</th>
                        <th>Qty</th>
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
                        </tr>
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
    @endforeach

    @if ($visit->prescriptions->isEmpty())
        <p class="text-muted">Belum ada resep.</p>
    @endif

    @can('prescription.create')
        @if ($writable)
            <form action="{{ route('care.prescription-item.store', $visit) }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-4">
                        <x-select
                            name="medicine_id"
                            label="Obat"
                            :options="$medicines->mapWithKeys(fn ($medicine) => [$medicine->id => $medicine->name.' ('.$medicine->unitLabel().')'])->all()"
                            :selected="old('medicine_id')"
                            required
                            placeholder="Pilih obat"
                        />
                    </div>
                    <div class="col-md-2">
                        <x-input name="dosage" label="Dosis" :value="old('dosage')" />
                    </div>
                    <div class="col-md-2">
                        <x-input name="frequency" label="Frekuensi" :value="old('frequency')" />
                    </div>
                    <div class="col-md-2">
                        <x-input name="duration" label="Durasi" :value="old('duration')" />
                    </div>
                    <div class="col-md-2">
                        <x-input name="quantity" type="number" label="Qty" :value="old('quantity', 1)" required />
                    </div>
                </div>
                <x-button type="submit" icon="bx bx-plus">Tambah ke Draft</x-button>
            </form>
        @endif
    @endcan
</x-card>
