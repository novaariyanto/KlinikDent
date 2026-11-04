@php
    $describe = fn (array $items) => $items ? \App\Support\Clinical\ToothConditions::summary($items) : 'Sehat / belum dicatat';
@endphp

<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('patients.history.show', $patient) }}" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="odontogram">
            <div class="col-md-4">
                <label class="form-label" for="oa">Bandingkan dari</label>
                <select id="oa" name="oa" class="form-select">
                    <option value="">— Pilih kunjungan —</option>
                    @foreach ($compareOptions as $visit)
                        <option value="{{ $visit->id }}" @selected(($compareA['key'] ?? null) === (string) $visit->id)>
                            {{ $visit->visit_date?->format('d M Y') }} — {{ $visit->doctor?->name ?: 'Tanpa dokter' }}{{ $changedVisits->contains('id', $visit->id) ? ' •' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="ob">Dengan</label>
                <select id="ob" name="ob" class="form-select">
                    <option value="current" @selected(($compareB['key'] ?? null) === 'current')>Kondisi terkini (odontogram aktif)</option>
                    @foreach ($compareOptions as $visit)
                        <option value="{{ $visit->id }}" @selected(($compareB['key'] ?? null) === (string) $visit->id)>
                            {{ $visit->visit_date?->format('d M Y') }} — {{ $visit->doctor?->name ?: 'Tanpa dokter' }}{{ $changedVisits->contains('id', $visit->id) ? ' •' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-primary"><i class="bx bx-git-compare me-1"></i>Bandingkan</button>
            </div>
            <div class="col-12 small text-muted">• = kunjungan yang mengubah odontogram. Kondisi tiap kunjungan adalah keadaan setelah kunjungan tersebut, bukan kondisi terkini.</div>
        </form>
    </div>
</div>

<div class="row g-3">
    @foreach ([$compareA, $compareB] as $side)
        <div class="col-xxl-6">
            <x-card :title="$side['label'] ?? 'Belum dipilih'">
                @if ($side)
                    @if ($side['state'])
                        @include('clinical.history.partials.odontogram-chart', ['state' => $side['state']])
                    @else
                        <p class="text-muted mb-0">Belum ada temuan odontogram pada titik ini.</p>
                    @endif
                @else
                    <p class="text-muted mb-0">Pilih kunjungan untuk ditampilkan.</p>
                @endif
            </x-card>
        </div>
    @endforeach
</div>

@if ($compareA && $compareB)
    <x-card title="Perbedaan">
        @if ($compareDiff)
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th style="width:70px">Gigi</th><th>{{ $compareA['label'] }}</th><th>{{ $compareB['label'] }}</th></tr></thead>
                    <tbody>
                        @foreach ($compareDiff as $number => $row)
                            <tr>
                                <td class="fw-semibold">{{ $number }}</td>
                                <td class="text-muted">{{ $describe($row['before']) }}</td>
                                <td>{{ $describe($row['after']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0">Tidak ada perbedaan kondisi gigi.</p>
        @endif
    </x-card>
@endif

<x-card title="Perubahan dari waktu ke waktu">
    @forelse ($changedVisits as $visit)
        <div class="ph-odo-step">
            <div class="ph-odo-step__head">
                <span class="fw-semibold">{{ $visit->visit_date?->format('d M Y') }}</span>
                <span class="text-muted small">{{ $visit->doctor?->name ?: 'Tanpa dokter' }}</span>
            </div>
            <ul class="ph-odo-changes">
                @foreach ($timeline->changesIn($visit->id) as $number => $change)
                    <li>
                        <strong>{{ $number }}</strong>
                        <span class="text-muted">{{ $describe($change['before']) }}</span>
                        <i class="bx bx-right-arrow-alt"></i>
                        <span>{{ $change['after'] ? \App\Support\Clinical\ToothConditions::summary($change['after']) : 'Sehat / kondisi dihapus' }}</span>
                        @if ($change['approximate'])
                            <span class="badge bg-light text-muted border" title="Data lama hanya menyimpan kode kondisi utama">perkiraan</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <p class="text-muted mb-0">Belum ada riwayat perubahan odontogram.</p>
    @endforelse
</x-card>
