<x-card title="Tindakan">
    <x-table>
        <thead>
            <tr>
                <th>Tindakan</th>
                <th>Gigi</th>
                <th>Qty</th>
                <th>Tarif saat dicatat</th>
                <th>Status tagihan</th>
                @can('procedure.manage')<th></th>@endcan
            </tr>
        </thead>
        <tbody>
            @forelse ($visit->procedureRecords as $item)
                <tr>
                    <td>{{ $item->procedure?->name }}</td>
                    <td>{{ $item->tooth_number ?: '-' }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>Rp {{ number_format((float) $item->price_at_time, 0, ',', '.') }}</td>
                    <td>{{ $item->billing_status->label() }}</td>
                    @can('procedure.manage')
                        <td class="text-end">
                            @if ($writable && $item->isUnbilled())
                                <form action="{{ route('care.procedure.destroy', [$visit, $item]) }}" method="POST" onsubmit="return confirm('Hapus tindakan ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-soft-danger" type="submit">Hapus</button>
                                </form>
                            @endif
                        </td>
                    @endcan
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted">Belum ada tindakan. Tarif akan di-snapshot saat dicatat.</td></tr>
            @endforelse
        </tbody>
    </x-table>

    @can('procedure.manage')
        @if ($writable)
            <form action="{{ route('care.procedure.store', $visit) }}" method="POST" class="mt-3">
                @csrf
                <div class="row">
                    <div class="col-md-6">
                        <x-select
                            name="procedure_id"
                            label="Tindakan"
                            :options="$procedures->mapWithKeys(fn ($procedure) => [$procedure->id => $procedure->code.' — '.$procedure->name])->all()"
                            :selected="old('procedure_id')"
                            required
                            placeholder="Pilih tindakan"
                        />
                    </div>
                    <div class="col-md-3">
                        <x-input name="tooth_number" label="Gigi (opsional)" :value="old('tooth_number')" />
                    </div>
                    <div class="col-md-3">
                        <x-input name="quantity" type="number" label="Qty" :value="old('quantity', 1)" required />
                    </div>
                </div>
                <x-button type="submit" icon="bx bx-plus">Tambah Tindakan</x-button>
            </form>
        @endif
    @endcan
</x-card>
