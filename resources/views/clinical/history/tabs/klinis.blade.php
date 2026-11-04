<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('patients.history.show', $patient) }}" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="klinis">
            <div class="col-md-2 col-sm-6">
                <label class="form-label" for="k-from">Dari</label>
                <input type="date" id="k-from" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-2 col-sm-6">
                <label class="form-label" for="k-to">Sampai</label>
                <input type="date" id="k-to" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-3 col-sm-6">
                <label class="form-label" for="k-dq">Diagnosis (kode / nama)</label>
                <input type="search" id="k-dq" name="dq" value="{{ $filters['dq'] ?? '' }}" class="form-control" placeholder="mis. K02.1">
            </div>
            <div class="col-md-3 col-sm-6">
                <label class="form-label" for="k-pq">Tindakan (nama / ICD-9)</label>
                <input type="search" id="k-pq" name="pq" value="{{ $filters['pq'] ?? '' }}" class="form-control" placeholder="mis. tambal">
            </div>
            <div class="col-md-auto d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bx bx-filter-alt me-1"></i>Terapkan</button>
                <a href="{{ route('patients.history.show', ['patient' => $patient, 'tab' => 'klinis']) }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

@if ($diagnosisSummary->isNotEmpty())
    <div class="ph-chips mb-3">
        @foreach ($diagnosisSummary as $row)
            <span class="ph-chip" title="Pertama {{ \Illuminate\Support\Carbon::parse($row->first_date)->format('d M Y') }}">
                <span class="ph-dx__code">{{ $row->code ?: '—' }}</span> {{ \Illuminate\Support\Str::limit($row->description, 40) }}
                <span class="ph-chip__count">{{ $row->visit_count }}×</span>
            </span>
        @endforeach
    </div>
@endif

<div class="row g-3">
    <div class="col-xl-6">
        <x-card :title="'Riwayat diagnosis ('.$diagnoses->total().')'">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Tanggal</th><th>Diagnosis</th><th>Jenis</th><th>Gigi</th><th>Dokter</th></tr></thead>
                    <tbody>
                        @forelse ($diagnoses as $diagnosis)
                            <tr>
                                <td class="text-nowrap">{{ $diagnosis->visit?->visit_date?->format('d M Y') }}</td>
                                <td>
                                    <span class="ph-dx__code">{{ $diagnosis->code ?: '—' }}</span>
                                    <span class="{{ $diagnosis->rank === \App\Enums\DiagnosisRank::Primary ? 'fw-semibold' : '' }}">{{ $diagnosis->description }}</span>
                                    @if ($diagnosis->rank === \App\Enums\DiagnosisRank::Primary)
                                        <span class="badge bg-primary">Utama</span>
                                    @endif
                                </td>
                                <td class="small">{{ $diagnosis->kind?->label() ?? '-' }}</td>
                                <td>{{ $diagnosis->tooth_number ?: '-' }}</td>
                                <td class="small">{{ $diagnosis->visit?->doctor?->name ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">Belum ada data diagnosis.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-2">{{ $diagnoses->links() }}</div>
        </x-card>
    </div>
    <div class="col-xl-6">
        <x-card :title="'Riwayat tindakan ('.$procedures->total().')'">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Tanggal</th><th>Tindakan</th><th>Gigi</th><th class="text-end">Jml</th><th>Dokter</th></tr></thead>
                    <tbody>
                        @forelse ($procedures as $procedure)
                            <tr>
                                <td class="text-nowrap">{{ $procedure->visit?->visit_date?->format('d M Y') }}</td>
                                <td>
                                    {{ $procedure->procedure?->name ?: 'Tindakan' }}
                                    @if ($procedure->icd9_code)
                                        <span class="text-muted small">ICD-9 {{ $procedure->icd9_code }}</span>
                                    @endif
                                </td>
                                <td>{{ $procedure->tooth_number ?: '-' }}</td>
                                <td class="text-end">{{ $procedure->quantity }}</td>
                                <td class="small">{{ $procedure->visit?->doctor?->name ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">Belum ada data tindakan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-2">{{ $procedures->links() }}</div>
        </x-card>
    </div>
</div>
