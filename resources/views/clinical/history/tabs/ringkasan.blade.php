<div class="row g-3">
    <div class="col-lg-7">
        <x-card title="Kunjungan terakhir">
            @forelse ($recentVisits as $visit)
                @php $primary = $visit->diagnoses->first(fn ($d) => $d->rank === \App\Enums\DiagnosisRank::Primary) ?? $visit->diagnoses->sortBy('id')->first(); @endphp
                <div class="ph-mini">
                    <div class="ph-mini__head">
                        <span class="fw-semibold">{{ $visit->visit_date?->format('d M Y') }}</span>
                        <span class="{{ $visit->status->badgeClass() }}">{{ $visit->status->label() }}</span>
                        <span class="text-muted small">{{ $visit->doctor?->name ?: 'Tanpa dokter' }} · {{ $visit->branch?->name }}</span>
                    </div>
                    <div class="small">Keluhan: {{ $visit->medicalRecord?->chief_complaint ?: 'Belum ada data' }}</div>
                    <div class="small">Diagnosis: {{ $primary ? trim(($primary->code ? $primary->code.' ' : '').$primary->description) : 'Belum ada data' }}</div>
                    <div class="small">Tindakan: {{ $visit->procedureRecords->map(fn ($p) => $p->procedure?->name)->filter()->implode(', ') ?: 'Belum ada data' }}</div>
                </div>
            @empty
                <p class="text-muted mb-0">Belum ada kunjungan.</p>
            @endforelse
            <x-slot:footer>
                <a href="{{ route('patients.history.show', ['patient' => $patient, 'tab' => 'kunjungan']) }}">Lihat semua kunjungan <i class="bx bx-right-arrow-alt"></i></a>
            </x-slot:footer>
        </x-card>
    </div>
    <div class="col-lg-5">
        <x-card title="Jadwal kontrol berikutnya">
            @if ($nextControl)
                <p class="mb-0"><span class="fw-semibold">{{ $nextControl->control_date->format('d M Y') }}</span>
                    <span class="text-muted small">(dari kunjungan #{{ $nextControl->visit_id }})</span></p>
            @else
                <p class="text-muted mb-0">Belum ada data jadwal kontrol mendatang.</p>
            @endif
        </x-card>

        <x-card title="Diagnosis tersering / terbaru">
            @forelse ($diagnosisSummary as $row)
                <div class="d-flex justify-content-between gap-2 py-1 border-bottom">
                    <span><span class="ph-dx__code">{{ $row->code ?: '—' }}</span> {{ $row->description }}</span>
                    <span class="text-muted small text-nowrap">{{ $row->visit_count }} kunjungan · {{ \Illuminate\Support\Carbon::parse($row->last_date)->format('d M Y') }}</span>
                </div>
            @empty
                <p class="text-muted mb-0">Belum ada data diagnosis.</p>
            @endforelse
        </x-card>

        <x-card title="Temuan odontogram aktif">
            @forelse ($currentTeeth as $number => $items)
                <div class="d-flex gap-2 py-1 border-bottom">
                    <span class="fw-semibold ph-tooth-no">{{ $number }}</span>
                    <span class="small">{{ \App\Support\Clinical\ToothConditions::summary($items) }}</span>
                </div>
            @empty
                <p class="text-muted mb-0">Belum ada temuan odontogram.</p>
            @endforelse
        </x-card>
    </div>
</div>
