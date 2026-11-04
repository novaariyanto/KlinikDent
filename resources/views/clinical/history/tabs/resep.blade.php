@php $canPrint = auth()->user()->can('prescription.view'); @endphp
<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('patients.history.show', $patient) }}" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="resep">
            <div class="col-md-2 col-sm-6">
                <label class="form-label" for="r-from">Dari</label>
                <input type="date" id="r-from" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-2 col-sm-6">
                <label class="form-label" for="r-to">Sampai</label>
                <input type="date" id="r-to" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-3 col-sm-6">
                <label class="form-label" for="r-mq">Nama obat</label>
                <input type="search" id="r-mq" name="mq" value="{{ $filters['mq'] ?? '' }}" class="form-control" placeholder="mis. amoxicillin">
            </div>
            <div class="col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bx bx-filter-alt me-1"></i>Terapkan</button>
                <a href="{{ route('patients.history.show', ['patient' => $patient, 'tab' => 'resep']) }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<x-card :title="'Riwayat obat ('.$items->total().' item)'">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Obat</th>
                    <th>Dosis</th>
                    <th>Frekuensi</th>
                    <th>Aturan pakai</th>
                    <th class="text-end">Jumlah</th>
                    <th>Penulis resep</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    @php
                        $prescription = $item->prescription;
                        $firstOfPrescription = $loop->first || $items[$loop->index - 1]->prescription_id !== $item->prescription_id;
                    @endphp
                    <tr class="{{ $firstOfPrescription && ! $loop->first ? 'ph-row-sep' : '' }}">
                        <td class="text-nowrap">{{ $firstOfPrescription ? $prescription?->visit?->visit_date?->format('d M Y') : '' }}</td>
                        <td>
                            <span class="fw-semibold">{{ $item->label() }}</span>
                            @if ($item->isCompound() && $item->ingredients->isNotEmpty())
                                <div class="small text-muted">{{ $item->ingredients->map(fn ($i) => trim($i->name.' '.$i->quantity.' '.$i->unit))->implode('; ') }}</div>
                            @endif
                        </td>
                        <td>{{ $item->dosage ?: '-' }}</td>
                        <td>{{ $item->frequency ?: '-' }}</td>
                        <td>{{ $item->duration ?: '-' }}</td>
                        <td class="text-end">{{ $item->quantity ?? '-' }}</td>
                        <td class="small">{{ $prescription?->doctor?->name ?: ($prescription?->visit?->doctor?->name ?: '-') }}</td>
                        <td class="text-end text-nowrap">
                            @if ($firstOfPrescription)
                                <span class="{{ $prescription->status->badgeClass() }}">{{ $prescription->status->label() }}</span>
                                @if ($canPrint)
                                    <a href="{{ route('patients.history.prescription.print', [$patient, $prescription]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary ms-1" title="Lihat / cetak resep"><i class="bx bx-printer"></i></a>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">Belum ada data resep.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-2">{{ $items->links() }}</div>
</x-card>
