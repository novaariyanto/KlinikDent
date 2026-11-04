@php
    $fmt = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('d M Y') : '-';
    $selected = collect(old('visit_ids', $eligibleVisits->pluck('id')->all()))->map(fn ($id) => (int) $id)->all();
@endphp

<x-card title="Resume medis pasien">
    <p class="text-muted small">Resume lintas kunjungan, terpisah dari rekam medis per kunjungan. Isi dibekukan saat dibuat dan tidak berubah bila rekam medis diperbarui.</p>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Nomor</th><th>Periode</th><th>Kunjungan</th><th>Dokter penyusun</th><th>Dibuat</th><th></th></tr></thead>
            <tbody>
                @forelse ($resumes as $document)
                    <tr class="{{ session('resume_created') === $document->id ? 'table-success' : '' }}">
                        <td class="text-nowrap fw-semibold">{{ $document->number }}</td>
                        <td class="text-nowrap">{{ $fmt(data_get($document->payload, 'period.from')) }} — {{ $fmt(data_get($document->payload, 'period.to')) }}</td>
                        <td>{{ count(data_get($document->payload, 'visit_ids', [])) }}</td>
                        <td>{{ data_get($document->payload, 'author.name', '-') }}</td>
                        <td class="text-nowrap">{{ $document->created_at?->format('d M Y H:i') }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('patients.history.resume.pdf', [$patient, $document]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="bx bx-show"></i> Lihat</a>
                            <a href="{{ route('patients.history.resume.pdf', [$patient, $document, 'download' => 1]) }}" class="btn btn-sm btn-outline-secondary"><i class="bx bx-download"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">Belum ada resume medis pasien.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-2">{{ $resumes->links() }}</div>
</x-card>

@if ($canCreateResume)
    <x-card title="Buat resume medis baru">
        <form method="GET" action="{{ route('patients.history.show', $patient) }}" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="tab" value="resume">
            <div class="col-md-3 col-sm-6">
                <label class="form-label" for="rs-from">Periode dari</label>
                <input type="date" id="rs-from" name="resume_from" value="{{ $resumeFrom }}" class="form-control">
            </div>
            <div class="col-md-3 col-sm-6">
                <label class="form-label" for="rs-to">Sampai</label>
                <input type="date" id="rs-to" name="resume_to" value="{{ $resumeTo }}" class="form-control">
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-outline-primary"><i class="bx bx-refresh me-1"></i>Susun ulang</button>
            </div>
        </form>

        @if (! $resumeDraft)
            <p class="text-muted mb-0">Tidak ada kunjungan (selain yang dibatalkan) pada periode ini.</p>
        @else
            <form method="POST" action="{{ route('patients.history.resume.store', $patient) }}">
                @csrf
                <div class="ph-subhead">Kunjungan yang dicakup</div>
                @error('visit_ids')<div class="text-danger small mb-1">{{ $message }}</div>@enderror
                <div class="ph-checks mb-3">
                    @foreach ($resumeDraft['visits'] as $visit)
                        <label class="form-check">
                            <input type="checkbox" class="form-check-input" name="visit_ids[]" value="{{ $visit['id'] }}" @checked(in_array($visit['id'], $selected, true))>
                            <span class="form-check-label">{{ $fmt($visit['date']) }} · {{ $visit['number'] }} · {{ $visit['doctor'] ?: 'Tanpa dokter' }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-lg-4">
                        <div class="ph-subhead">Keluhan</div>
                        @forelse ($resumeDraft['complaints'] as $row)
                            <div class="small">{{ $fmt($row['date']) }}: {{ $row['text'] }}</div>
                        @empty
                            <div class="small text-muted">Belum ada data</div>
                        @endforelse
                    </div>
                    <div class="col-lg-4">
                        <div class="ph-subhead">Diagnosis</div>
                        @forelse ($resumeDraft['diagnoses'] as $row)
                            <div class="small">
                                <span class="ph-dx__code">{{ $row['code'] ?: '—' }}</span> {{ $row['description'] }}
                                @if ($row['teeth']) <span class="text-muted">gigi {{ implode(', ', $row['teeth']) }}</span>@endif
                                <span class="text-muted">· {{ $row['visits'] }}×</span>
                            </div>
                        @empty
                            <div class="small text-muted">Belum ada data</div>
                        @endforelse
                    </div>
                    <div class="col-lg-4">
                        <div class="ph-subhead">Tindakan & terapi</div>
                        @foreach ($resumeDraft['procedures'] as $row)
                            <div class="small">{{ $row['name'] }} × {{ $row['count'] }}</div>
                        @endforeach
                        @foreach ($resumeDraft['therapy'] as $row)
                            <div class="small text-muted">{{ $row['label'] }}{{ $row['rule'] !== '' ? ' — '.$row['rule'] : '' }}</div>
                        @endforeach
                        @if (! $resumeDraft['procedures'] && ! $resumeDraft['therapy'])
                            <div class="small text-muted">Belum ada data</div>
                        @endif
                    </div>
                </div>
                <p class="small text-muted">Keluhan, diagnosis, tindakan, dan terapi diambil otomatis dari kunjungan yang dicentang saat disimpan. Bagian di bawah dapat disunting dokter.</p>

                @foreach ([
                    'relevant_history' => 'Riwayat relevan',
                    'progress' => 'Perkembangan kondisi',
                    'last_condition' => 'Kondisi terakhir',
                    'plan' => 'Rencana lanjutan',
                ] as $field => $label)
                    <div class="mb-3">
                        <label class="form-label" for="rs-{{ $field }}">{{ $label }}</label>
                        <textarea id="rs-{{ $field }}" name="{{ $field }}" rows="3" class="form-control @error($field) is-invalid @enderror">{{ old($field, $resumeDraft['defaults'][$field] ?? '') }}</textarea>
                        @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endforeach

                <div class="d-flex justify-content-between align-items-center">
                    <span class="small text-muted">Dokter penyusun: {{ auth()->user()->name }}</span>
                    <button type="submit" class="btn btn-primary"><i class="bx bx-save me-1"></i>Simpan & terbitkan resume</button>
                </div>
            </form>
        @endif
    </x-card>
@endif
