<section class="care-section" id="tindakan">
    <div class="care-section__head">
        <h5 class="care-section__title">Tindakan</h5>
        <p class="care-section__hint">Cari master tindakan. Gigi opsional dari temuan odontogram. Tarif di-snapshot saat dicatat.</p>
    </div>
    <div class="care-section__body">
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
                    <tr><td colspan="6" class="text-muted">Belum ada tindakan.</td></tr>
                @endforelse
            </tbody>
        </x-table>

        @can('procedure.manage')
            @if ($writable)
                <form action="{{ route('care.procedure.store', $visit) }}" method="POST" class="mt-3">
                    @csrf
                    <div class="care-typeahead mb-3" data-typeahead data-typeahead-items="{{ json_encode($procedures->map(fn ($procedure) => ['id' => $procedure->id, 'code' => $procedure->code, 'label' => $procedure->code.' — '.$procedure->name])->values()) }}">
                        <label class="form-label">Cari tindakan</label>
                        <input type="search" class="form-control" data-typeahead-q placeholder="Nama atau kode tindakan" autocomplete="off" value="{{ old('procedure_id') ? ($procedures->firstWhere('id', old('procedure_id'))?->name) : '' }}">
                        <input type="hidden" name="procedure_id" data-typeahead-id value="{{ old('procedure_id') }}" required>
                        <div class="care-typeahead__list" data-typeahead-results hidden></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            @include('clinical.care.partials.tooth-select', ['id' => 'procedure-tooth', 'selected' => old('tooth_number')])
                        </div>
                        <div class="col-md-6">
                            <x-input name="quantity" type="number" label="Qty" :value="old('quantity', 1)" required />
                        </div>
                    </div>
                    <x-button type="submit" icon="bx bx-plus">Tambah Tindakan</x-button>
                </form>
            @endif
        @endcan
    </div>
</section>
