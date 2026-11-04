<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('patients.history.show', $patient) }}" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="dokumen">
            <div class="col-md-3 col-sm-6">
                <label class="form-label" for="d-type">Jenis dokumen</label>
                <select id="d-type" name="doc_type" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($categories as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['doc_type'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 col-sm-6">
                <label class="form-label" for="d-from">Dari</label>
                <input type="date" id="d-from" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-2 col-sm-6">
                <label class="form-label" for="d-to">Sampai</label>
                <input type="date" id="d-to" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bx bx-filter-alt me-1"></i>Terapkan</button>
                <a href="{{ route('patients.history.show', ['patient' => $patient, 'tab' => 'dokumen']) }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<x-card :title="'Dokumen medis ('.$documents->total().')'">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Tanggal</th><th>Jenis</th><th>Nomor</th><th>Dokter</th><th>Status</th><th>Kunjungan</th><th></th></tr></thead>
            <tbody>
                @forelse ($documents as $row)
                    @php $allowed = $row['allowed']; @endphp
                    <tr>
                        <td class="text-nowrap">{{ $row['date']?->format('d M Y') ?? '-' }}</td>
                        <td>
                            <span class="badge bg-light text-dark border">{{ $categories[$row['category']] ?? $row['category'] }}</span>
                            <div class="small">{{ $row['type'] }}</div>
                        </td>
                        <td class="text-nowrap">{{ $row['number'] ?: '-' }}</td>
                        <td class="small">{{ $row['doctor'] ?: '-' }}</td>
                        <td><span class="{{ $row['status_class'] }}">{{ $row['status'] }}</span></td>
                        <td class="small text-nowrap">{{ $row['visit_date']?->format('d M Y') ?? '-' }}</td>
                        <td class="text-end text-nowrap">
                            @if ($allowed)
                                <a href="{{ $row['preview'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary" title="Pratinjau"><i class="bx bx-show"></i></a>
                                @if ($row['print'])
                                    <a href="{{ $row['print'] }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Cetak"><i class="bx bx-printer"></i></a>
                                @endif
                            @else
                                <span class="text-muted small">Tidak ada akses</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">Belum ada dokumen medis.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="small text-muted mt-2 mb-0">Dokumen berstatus "Belum diterbitkan" / "Belum dicetak" akan mendapat nomor dan kode verifikasi saat pertama kali dibuka.</p>
    <div class="mt-2">{{ $documents->links() }}</div>
</x-card>
