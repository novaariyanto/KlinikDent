@php
    $hasFilter = collect(['date_from', 'date_to', 'doctor_id', 'branch_id', 'room_id', 'status', 'q'])->contains(fn ($k) => filled($filters[$k] ?? null));
@endphp
<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('patients.history.show', $patient) }}" class="row g-2 align-items-end ph-filter">
            <input type="hidden" name="tab" value="kunjungan">
            <div class="col-md-3 col-sm-6">
                <label class="form-label" for="f-q">Cari no. kunjungan / diagnosis</label>
                <input type="search" id="f-q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="mis. 202611030023 atau K02">
            </div>
            <div class="col-md-2 col-sm-6">
                <label class="form-label" for="f-from">Dari</label>
                <input type="date" id="f-from" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-2 col-sm-6">
                <label class="form-label" for="f-to">Sampai</label>
                <input type="date" id="f-to" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
            </div>
            <div class="col-md-2 col-sm-6">
                <label class="form-label" for="f-doctor">Dokter</label>
                <select id="f-doctor" name="doctor_id" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($options['doctors'] as $doctor)
                        <option value="{{ $doctor->id }}" @selected((string) ($filters['doctor_id'] ?? '') === (string) $doctor->id)>{{ $doctor->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 col-sm-6">
                <label class="form-label" for="f-status">Status</label>
                <select id="f-status" name="status" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($options['statuses'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if ($options['branches']->count() > 1)
                <div class="col-md-3 col-sm-6">
                    <label class="form-label" for="f-branch">Cabang</label>
                    <select id="f-branch" name="branch_id" class="form-select">
                        <option value="">Semua</option>
                        @foreach ($options['branches'] as $branch)
                            <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-3 col-sm-6">
                <label class="form-label" for="f-room">Jenis pelayanan / poli</label>
                <select id="f-room" name="room_id" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($options['rooms'] as $room)
                        <option value="{{ $room->id }}" @selected((string) ($filters['room_id'] ?? '') === (string) $room->id)>{{ $room->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto col-sm-6 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bx bx-filter-alt me-1"></i>Terapkan</button>
                @if ($hasFilter)
                    <a href="{{ route('patients.history.show', $patient) }}" class="btn btn-outline-secondary">Reset</a>
                @endif
            </div>
        </form>
    </div>
</div>

<p class="text-muted small mb-2">{{ $visits->total() }} kunjungan{{ $hasFilter ? ' sesuai filter' : '' }}, terbaru di atas. Klik kartu untuk membuka rekam medis kunjungan.</p>

<div class="ph-timeline" id="ph-visits">
    @forelse ($visits as $visit)
        @php
            $number = (new \App\Support\Clinical\ExaminationQueueRow($visit))->registrationNumber();
            $primary = $visit->diagnoses->first(fn ($d) => $d->rank === \App\Enums\DiagnosisRank::Primary) ?? $visit->diagnoses->sortBy('id')->first();
            $invoice = $visit->invoices->sortByDesc('id')->first();
        @endphp
        <div class="ph-visit card" data-visit="{{ $visit->id }}">
            <button type="button" class="ph-visit__head" data-ph-toggle="{{ route('patients.history.visit', [$patient, $visit]) }}" aria-expanded="false" aria-controls="ph-visit-body-{{ $visit->id }}">
                <div class="ph-visit__date">
                    <span class="ph-visit__day">{{ $visit->visit_date?->format('d M Y') }}</span>
                    <span class="ph-visit__time">{{ $visit->queue?->created_at?->format('H:i') ?? $visit->created_at?->format('H:i') }}</span>
                </div>
                <div class="ph-visit__main">
                    <div class="ph-visit__line">
                        <span class="ph-visit__number">No. {{ $number }}</span>
                        <span class="{{ $visit->status->badgeClass() }}">{{ $visit->status->label() }}</span>
                        @if ($invoice)
                            <span class="{{ $invoice->status->badgeClass() }}">{{ $invoice->status->label() }}</span>
                        @else
                            <span class="badge bg-light text-muted border">Belum ada tagihan</span>
                        @endif
                        @if ($visit->medicalRecord?->finalized_at)
                            <span class="badge bg-success-subtle text-success border">RM final</span>
                        @endif
                    </div>
                    <div class="ph-visit__meta">
                        <span><i class="bx bx-user"></i> {{ $visit->doctor?->name ?: 'Tanpa dokter' }}</span>
                        <span><i class="bx bx-building"></i> {{ $visit->branch?->name ?: '-' }}{{ $visit->room ? ' · '.$visit->room->name : '' }}</span>
                        @if ($visit->payer)
                            <span><i class="bx bx-shield"></i> {{ $visit->payer->name }}</span>
                        @endif
                    </div>
                    <dl class="ph-visit__facts">
                        <dt>Keluhan</dt>
                        <dd>{{ $visit->medicalRecord?->chief_complaint ?: 'Belum ada data' }}</dd>
                        <dt>Diagnosis</dt>
                        <dd>
                            @if ($primary)
                                <strong>{{ trim(($primary->code ? $primary->code.' ' : '').$primary->description) }}</strong>
                                @if ($primary->tooth_number) <span class="text-muted">gigi {{ $primary->tooth_number }}</span>@endif
                                @if ($visit->diagnoses->count() > 1)
                                    <span class="text-muted">+{{ $visit->diagnoses->count() - 1 }} lainnya</span>
                                @endif
                            @else
                                Belum ada data
                            @endif
                        </dd>
                        <dt>Tindakan</dt>
                        <dd>
                            {{ $visit->procedureRecords->map(fn ($p) => ($p->procedure?->name ?: 'Tindakan').($p->tooth_number ? ' ('.$p->tooth_number.')' : ''))->implode(', ') ?: 'Belum ada data' }}
                        </dd>
                    </dl>
                    <div class="ph-visit__chips">
                        @if ($visit->prescriptions_count)
                            <span class="badge bg-light text-dark border"><i class="bx bx-capsule"></i> {{ $visit->prescriptions_count }} resep</span>
                        @endif
                        @if ($visit->referrals_count)
                            <span class="badge bg-light text-dark border"><i class="bx bx-send"></i> {{ $visit->referrals_count }} rujukan</span>
                        @endif
                    </div>
                </div>
                <i class="bx bx-chevron-down ph-visit__chevron" aria-hidden="true"></i>
            </button>
            <div class="ph-visit__body" id="ph-visit-body-{{ $visit->id }}" hidden>
                <div class="ph-visit__loading text-muted"><span class="spinner-border spinner-border-sm me-2"></span>Memuat rekam medis…</div>
            </div>
        </div>
    @empty
        <div class="card"><div class="card-body text-muted">{{ $hasFilter ? 'Tidak ada kunjungan yang sesuai filter.' : 'Belum ada kunjungan.' }}</div></div>
    @endforelse
</div>

<div class="mt-3">{{ $visits->links() }}</div>

<script>
(function () {
    var list = document.getElementById('ph-visits');
    if (!list) return;

    list.addEventListener('click', function (event) {
        var head = event.target.closest('[data-ph-toggle]');
        if (!head || event.target.closest('a')) return;

        var card = head.closest('.ph-visit');
        var body = card.querySelector('.ph-visit__body');
        var open = head.getAttribute('aria-expanded') === 'true';

        head.setAttribute('aria-expanded', open ? 'false' : 'true');
        card.classList.toggle('is-open', !open);
        body.hidden = open;

        if (open || body.dataset.loaded === '1') return;
        body.dataset.loaded = '1';

        fetch(head.getAttribute('data-ph-toggle'), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
            credentials: 'same-origin'
        }).then(function (response) {
            if (!response.ok) throw new Error(response.status);
            return response.text();
        }).then(function (html) {
            body.innerHTML = html;
        }).catch(function () {
            body.dataset.loaded = '';
            body.innerHTML = '<div class="alert alert-danger mb-0">Rekam medis kunjungan gagal dimuat. Tutup lalu buka kembali untuk mencoba lagi.</div>';
        });
    });
})();
</script>
