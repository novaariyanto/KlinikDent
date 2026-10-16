@php
    $summary = $checklist['summary'] ?? [];
    $sections = $checklist['sections'] ?? [];
    $overallPct = $summary['overall_pct'] ?? null;
    $criticalGaps = (int) ($summary['critical_gaps'] ?? 0);
    $statusMeta = [
        'pass' => ['label' => 'Lulus', 'class' => 'success', 'icon' => 'bx-check-circle'],
        'incomplete' => ['label' => 'Belum lengkap', 'class' => 'warning', 'icon' => 'bx-error-circle'],
        'action_needed' => ['label' => 'Perlu tindakan', 'class' => 'info', 'icon' => 'bx-wrench'],
        'error' => ['label' => 'Error', 'class' => 'danger', 'icon' => 'bx-x-circle'],
        'unchecked' => ['label' => 'Belum diperiksa', 'class' => 'secondary', 'icon' => 'bx-help-circle'],
    ];
@endphp

<x-card title="Kesiapan Integrasi SATUSEHAT">
    <p class="text-muted small mb-2">
        Cabang:
        <strong class="text-body">{{ $summary['branch_name'] ?? '—' }}</strong>
        · aktivasi modul ≠ kesiapan sync
    </p>

    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <span class="small fw-medium">Progres persyaratan wajib</span>
            <span class="small text-muted">
                @if ($overallPct === null)
                    Belum diperiksa
                @else
                    {{ $overallPct }}% ({{ $summary['pass'] ?? 0 }}/{{ $summary['total'] ?? 0 }})
                @endif
            </span>
        </div>
        <div class="progress" style="height: 8px">
            <div
                class="progress-bar bg-{{ $criticalGaps > 0 ? 'warning' : 'success' }}"
                role="progressbar"
                style="width: {{ $overallPct ?? 0 }}%"
                aria-valuenow="{{ $overallPct ?? 0 }}"
                aria-valuemin="0"
                aria-valuemax="100"
            ></div>
        </div>
        @if ($criticalGaps > 0)
            <p class="text-warning small mb-0 mt-1">
                {{ $criticalGaps }} persyaratan wajib belum terpenuhi.
            </p>
        @elseif ($overallPct !== null)
            <p class="text-success small mb-0 mt-1">Semua persyaratan wajib terpenuhi.</p>
        @endif
    </div>

    <ul class="list-unstyled small mb-3">
        <li class="d-flex justify-content-between gap-2 py-1 border-bottom">
            <span class="text-muted">Konfigurasi</span>
            <span>{{ $summary['config']['label'] ?? '—' }}</span>
        </li>
        <li class="d-flex justify-content-between gap-2 py-1 border-bottom">
            <span class="text-muted">Cabang</span>
            <span>{{ $summary['branch']['label'] ?? '—' }}</span>
        </li>
        <li class="d-flex justify-content-between gap-2 py-1 border-bottom">
            <span class="text-muted">Poli / ruangan</span>
            <span>{{ $summary['rooms']['label'] ?? '—' }}</span>
        </li>
        <li class="d-flex justify-content-between gap-2 py-1 border-bottom">
            <span class="text-muted">Pasien</span>
            <span>{{ $summary['patients']['label'] ?? '—' }}</span>
        </li>
        <li class="d-flex justify-content-between gap-2 py-1 border-bottom">
            <span class="text-muted">Dokter</span>
            <span>{{ $summary['doctors']['label'] ?? '—' }}</span>
        </li>
        <li class="d-flex justify-content-between gap-2 py-1 border-bottom">
            <span class="text-muted">Obat (KFA)</span>
            <span>{{ $summary['medicines']['label'] ?? '—' }}</span>
        </li>
        <li class="d-flex justify-content-between gap-2 py-1">
            <span class="text-muted">Koneksi API</span>
            <span class="fw-medium">{{ $summary['connection_label'] ?? 'Belum diuji' }}</span>
        </li>
    </ul>

    @if (! empty($summary['module_enabled']) && empty($summary['ready_to_sync']))
        <div class="alert alert-warning py-2 px-3 small">
            Modul sudah diaktifkan, tetapi integrasi belum siap sync.
            Lengkapi item wajib di bawah, lalu uji koneksi dan aktifkan sinkronisasi lokasi.
        </div>
    @elseif (! empty($summary['ready_to_sync']))
        <div class="alert alert-success py-2 px-3 small">
            Siap sync: modul aktif, kredensial tervalidasi, lokasi terpasang, sinkronisasi otomatis menyala.
        </div>
    @endif

    @foreach ($sections as $section)
        <div class="mb-3 {{ ! $loop->last ? 'pb-2 border-bottom' : '' }}">
            <h6 class="font-size-13 mb-2">{{ $section['title'] }}</h6>
            @forelse ($section['items'] as $item)
                @php
                    $meta = $statusMeta[$item['status'] ?? 'unchecked'] ?? $statusMeta['unchecked'];
                @endphp
                <div class="d-flex gap-2 mb-2">
                    <i class="bx {{ $meta['icon'] }} text-{{ $meta['class'] }} font-size-18 flex-shrink-0 mt-1"></i>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-1">
                            <span class="font-size-13 fw-medium">{{ $item['label'] }}</span>
                            <span class="badge bg-soft-{{ $meta['class'] }} text-{{ $meta['class'] }} font-size-11">{{ $meta['label'] }}</span>
                            @if (! ($item['required'] ?? true))
                                <span class="badge bg-light text-muted font-size-11">opsional</span>
                            @endif
                        </div>
                        @if (! empty($item['hint']))
                            <p class="text-muted small mb-0">{{ $item['hint'] }}</p>
                        @endif
                        @if (! empty($item['fix_url']) && ($item['status'] ?? '') !== 'pass')
                            <a href="{{ $item['fix_url'] }}" class="small">Perbaiki</a>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-muted small mb-0">Tidak ada item untuk diperiksa.</p>
            @endforelse
        </div>
    @endforeach
</x-card>
