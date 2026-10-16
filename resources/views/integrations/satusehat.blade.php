@extends('layouts.app')

@section('title', 'SATUSEHAT')

@section('content')
    <x-page-header
        title="Modul SATUSEHAT"
        :breadcrumb="['Integrasi' => route('integrations.index'), 'SATUSEHAT' => null]"
    />
    <x-alert />

    @php
        $openSettings = $errors->hasAny([
            'environment', 'base_url', 'token_url', 'organization_id',
            'client_id', 'client_secret', 'location_id', 'enabled', 'fake', 'sync_enabled',
        ]) || (
            ($branchSetting?->connection_ok ?? false)
            && ! filled($branchSetting?->location_id)
        );
    @endphp

    @if ($branches->isEmpty())
        <x-card title="Cabang">
            <p class="mb-0 text-muted">Klinik ini belum memiliki cabang.</p>
        </x-card>
    @else
        @php
            $ready = $settings->enabled && $branchSetting?->readyToSync();
            $setupGaps = collect([
                ! $settings->enabled ? 'Aktifkan modul SATUSEHAT' : null,
                ! filled($branchSetting?->organization_id) ? 'Isi Organization ID' : null,
                ! filled($branchSetting?->client_id) ? 'Isi Client ID' : null,
                ! $branchSetting?->hasSecret() || $branchSetting?->secretIsUnreadable() ? 'Isi Client Secret' : null,
                ! $branchSetting?->connection_ok ? 'Uji koneksi sampai berhasil' : null,
                ! filled($branchSetting?->location_id) ? 'Pilih lokasi pelayanan' : null,
                ! $branchSetting?->sync_enabled ? 'Centang “Aktifkan sinkronisasi otomatis” lalu simpan lokasi' : null,
            ])->filter()->values();
            $modeLabel = $settings->fake
                ? 'Sandbox lokal'
                : ($branchSetting?->isProduction() ? 'Production' : 'Sandbox Kemenkes');
            $statusText = $branchSetting?->statusLabel() ?: 'Belum dikonfigurasi';
            $statusTone = match (true) {
                $branchSetting?->secretIsUnreadable() || filled($branchSetting?->last_error) => 'danger',
                $ready => 'success',
                $branchSetting?->connection_ok => 'warning',
                default => 'secondary',
            };
            $choices = $locationChoices;
            if ($branchSetting?->location_id && ! array_key_exists($branchSetting->location_id, $choices)) {
                $choices[$branchSetting->location_id] = $branchSetting->location_name ?: $branchSetting->location_id;
            }
        @endphp

        {{-- Cabang + status singkat --}}
        <x-card>
            <div class="row g-3 align-items-end">
                <div class="col-md-5 col-lg-4">
                    <form method="GET" action="{{ route('integrations.satusehat') }}" id="ss-branch-form">
                        <x-select
                            name="branch_id"
                            id="ss-branch-id"
                            label="Cabang"
                            :options="$branches->mapWithKeys(fn ($item) => [$item->id => $item->name])->all()"
                            :selected="$branch?->id"
                            placeholder=""
                        />
                    </form>
                </div>
                <div class="col-md-7 col-lg-8">
                    <div class="d-flex flex-wrap align-items-center gap-2 pb-3">
                        <span class="badge bg-{{ $statusTone }} font-size-12">{{ $statusText }}</span>
                        <span class="badge bg-light text-dark font-size-12">{{ $modeLabel }}</span>
                        @if ($settings->enabled)
                            <span class="badge bg-soft-success text-success font-size-12">Modul aktif</span>
                        @else
                            <span class="badge bg-soft-secondary text-muted font-size-12">Modul nonaktif</span>
                        @endif
                        <span class="text-muted small ms-md-auto">
                            Sinkron terakhir:
                            {{ $stats['last_synced_at'] ? \Illuminate\Support\Carbon::parse($stats['last_synced_at'])->timezone(config('app.timezone'))->format('d M Y H:i') : 'belum ada' }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="row g-2 small text-muted">
                <div class="col-md-6">
                    Organisasi: <strong class="text-body">{{ $branchSetting?->organization_name ?: ($branchSetting?->organization_id ?: '—') }}</strong>
                </div>
                <div class="col-md-6">
                    Lokasi: <strong class="text-body">{{ $branchSetting?->location_name ?: ($branchSetting?->location_id ?: '—') }}</strong>
                </div>
            </div>

            @if ($branchSetting?->last_error)
                <div class="alert alert-danger py-2 px-3 mt-3 mb-0 small">{{ $branchSetting->last_error }}</div>
            @endif

            @if ($readiness['doctors_missing_nik'] > 0 || $readiness['patients_missing_nik'] > 0)
                <div class="alert alert-warning py-2 px-3 mt-3 mb-0 small">
                    Kelengkapan data:
                    @if ($readiness['doctors_missing_nik'] > 0)
                        <strong>{{ $readiness['doctors_missing_nik'] }}</strong> dokter tanpa NIK
                    @endif
                    @if ($readiness['doctors_missing_nik'] > 0 && $readiness['patients_missing_nik'] > 0)
                        ·
                    @endif
                    @if ($readiness['patients_missing_nik'] > 0)
                        <strong>{{ $readiness['patients_missing_nik'] }}</strong> pasien cabang ini tanpa NIK
                    @endif
                </div>
            @endif
        </x-card>

        {{-- Ringkasan antrean kirim --}}
        <div class="row">
            @foreach ([
                ['label' => 'Menunggu', 'value' => $stats['waiting'], 'icon' => 'bx bx-time-five', 'color' => 'warning'],
                ['label' => 'Antrian', 'value' => $stats['queued'], 'icon' => 'bx bx-loader', 'color' => 'info'],
                ['label' => 'Sebagian', 'value' => $stats['partial'] ?? 0, 'icon' => 'bx bx-git-branch', 'color' => 'warning'],
                ['label' => 'Lengkap', 'value' => $stats['synced'], 'icon' => 'bx bx-check-circle', 'color' => 'success'],
                ['label' => 'Gagal', 'value' => $stats['failed'], 'icon' => 'bx bx-error-circle', 'color' => 'danger'],
            ] as $stat)
                <div class="col-6 col-xl-3">
                    <div class="card mini-stats-wid">
                        <div class="card-body">
                            <div class="d-flex">
                                <div class="flex-grow-1">
                                    <p class="text-muted fw-medium mb-1">{{ $stat['label'] }}</p>
                                    <h4 class="mb-0">{{ $stat['value'] }}</h4>
                                </div>
                                <div class="avatar-sm rounded-circle bg-{{ $stat['color'] }} align-self-center mini-stat-icon">
                                    <span class="avatar-title rounded-circle bg-{{ $stat['color'] }}">
                                        <i class="{{ $stat['icon'] }} font-size-24"></i>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <ul class="nav nav-tabs nav-tabs-custom mb-3" id="ss-tabs" role="tablist">
            <li class="nav-item">
                <button
                    class="nav-link {{ $openSettings ? '' : 'active' }}"
                    type="button"
                    data-bs-toggle="tab"
                    data-bs-target="#ss-tab-kunjungan"
                    data-ss-tab="kunjungan"
                >Kunjungan</button>
            </li>
            <li class="nav-item">
                <button
                    class="nav-link {{ $openSettings ? 'active' : '' }}"
                    type="button"
                    data-bs-toggle="tab"
                    data-bs-target="#ss-tab-pengaturan"
                    data-ss-tab="pengaturan"
                >Pengaturan</button>
            </li>
            <li class="nav-item">
                <button
                    class="nav-link"
                    type="button"
                    data-bs-toggle="tab"
                    data-bs-target="#ss-tab-log"
                    data-ss-tab="log"
                >Log</button>
            </li>
        </ul>

        <div class="tab-content">
            {{-- Tab Kunjungan --}}
            <div class="tab-pane fade {{ $openSettings ? '' : 'show active' }}" id="ss-tab-kunjungan">
                <x-card title="Kunjungan selesai">
                    @can('satusehat.view')
                        @if (! $ready)
                            <div class="alert alert-warning py-2 px-3 small">
                                <p class="mb-1">Tombol Kirim terkunci sampai konfigurasi cabang siap (bukan karena kunjungan belum dipilih).</p>
                                @if ($setupGaps->isNotEmpty())
                                    <p class="mb-1 fw-medium">Yang masih kurang:</p>
                                    <ol class="mb-1 ps-3">
                                        @foreach ($setupGaps as $gap)
                                            <li>{{ $gap }}</li>
                                        @endforeach
                                    </ol>
                                @endif
                                @if ($canManage)
                                    Selesaikan di tab <strong>Pengaturan</strong>, lalu kembali ke sini.
                                @endif
                            </div>
                        @endif

                        <form method="POST" action="{{ route('integrations.satusehat.send') }}" class="row g-2 align-items-end mb-4">
                            @csrf
                            <div class="col-md-8 col-lg-9">
                                <x-select
                                    name="visit_id"
                                    label="Kirim / kirim ulang kunjungan"
                                    :options="$visits->mapWithKeys(fn ($visit) => [$visit->id => ($visit->patient?->name ?: 'Kunjungan').' · '.$visit->visit_date?->format('d M Y').' · '.($visit->satusehat_status?->label() ?: 'Belum dikirim')])->all()"
                                    :selected="old('visit_id')"
                                    placeholder="Pilih kunjungan"
                                    required
                                />
                            </div>
                            <div class="col-md-4 col-lg-3">
                                <div class="mb-3">
                                    @if ($ready)
                                        <x-button type="submit" class="w-100" icon="bx bx-send">Kirim</x-button>
                                    @else
                                        <x-button type="submit" class="w-100" disabled>Kirim</x-button>
                                    @endif
                                </div>
                            </div>
                        </form>
                    @endcan

                    <x-table>
                        <thead>
                            <tr>
                                <th>Pasien</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Encounter</th>
                                <th>Error</th>
                                <th class="text-end"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($visits as $visit)
                                <tr>
                                    <td>{{ $visit->patient?->name ?: '-' }}</td>
                                    <td>{{ $visit->visit_date?->format('d M Y') }}</td>
                                    <td>
                                        @if ($visit->satusehat_status)
                                            <span class="{{ $visit->satusehat_status->badgeClass() }}">{{ $visit->satusehat_status->label() }}</span>
                                        @else
                                            <span class="badge bg-secondary">Belum dikirim</span>
                                        @endif
                                    </td>
                                    <td class="font-size-13">{{ $visit->satusehat_id ?: '-' }}</td>
                                    <td class="text-muted small">{{ \Illuminate\Support\Str::limit($visitErrors->get($visit->id)?->error_message, 60) ?: '-' }}</td>
                                    <td class="text-end">
                                        <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-soft-secondary js-ss-log"
                                                data-url="{{ route('integrations.satusehat.visits.logs', $visit) }}"
                                                data-title="Log Encounter {{ $visit->satusehat_id ?: '#'.$visit->id }}"
                                            >Log</button>
                                            @if ($ready && $visit->satusehat_status === \App\Enums\SatuSehatStatus::Failed)
                                                <form method="POST" action="{{ route('integrations.satusehat.send') }}">
                                                    @csrf
                                                    <input type="hidden" name="visit_id" value="{{ $visit->id }}">
                                                    <button type="submit" class="btn btn-sm btn-warning">Ulangi</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-muted">Belum ada kunjungan selesai.</td></tr>
                            @endforelse
                        </tbody>
                    </x-table>
                </x-card>
            </div>

            {{-- Tab Pengaturan --}}
            <div class="tab-pane fade {{ $openSettings ? 'show active' : '' }}" id="ss-tab-pengaturan">
                <div class="row">
                    <div class="col-xl-7">
                        <x-card title="Kredensial cabang">
                            @if ($canManage)
                                <form method="POST" action="{{ route('integrations.satusehat.update') }}" data-ss-remember-tab="pengaturan">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                    <input type="hidden" name="enabled" value="0">
                                    <input type="hidden" name="fake" value="0">

                                    <div class="row g-2 mb-3">
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="enabled" id="ss-enabled" value="1" @checked(old('enabled', $settings->enabled))>
                                                <label class="form-check-label" for="ss-enabled">Aktifkan modul SATUSEHAT</label>
                                            </div>
                                            <p class="text-muted small mb-0 ms-4" id="ss-enabled-hint">
                                                Mencentang hanya mengaktifkan modul. Kesiapan sync tetap mengikuti checklist di kanan.
                                            </p>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="fake" id="ss-fake" value="1" @checked(old('fake', $settings->fake))>
                                                <label class="form-check-label" for="ss-fake">Sandbox lokal (tanpa API Kemenkes)</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="ss-enable-gaps" class="alert alert-warning py-2 px-3 small d-none" role="status"></div>

                                    <x-select
                                        name="environment"
                                        label="Environment"
                                        :options="['sandbox' => 'Sandbox', 'production' => 'Production']"
                                        :selected="old('environment', $branchSetting->environment ?: 'sandbox')"
                                        placeholder=""
                                        required
                                    />
                                    <x-input name="organization_id" label="Organization ID" :value="old('organization_id', $branchSetting->organization_id)" />
                                    <x-input name="client_id" label="Client ID" :value="old('client_id', $branchSetting->client_id)" />
                                    <x-input name="client_secret" type="password" label="Client Secret" placeholder="{{ $branchSetting->hasSecret() ? 'Sudah dikonfigurasi — isi ulang untuk mengganti' : '' }}" autocomplete="new-password" />
                                    @if ($branchSetting->hasSecret() && ! $branchSetting->secretIsUnreadable())
                                        <p class="text-muted small">Client Secret: <strong class="text-body">Sudah dikonfigurasi</strong> (nilai tidak ditampilkan).</p>
                                    @endif
                                    @if ($branchSetting->secretIsUnreadable())
                                        <p class="text-danger small">Client secret tersimpan tidak bisa dibuka. Isi ulang, lalu simpan.</p>
                                    @endif

                                    <details class="mb-3">
                                        <summary class="text-muted small" style="cursor: pointer">URL lanjutan (opsional)</summary>
                                        <div class="mt-2">
                                            <x-input name="base_url" label="Base URL" :value="old('base_url', $branchSetting->base_url ?: $branchSetting->baseUrl())" />
                                            <x-input name="token_url" label="Token URL" :value="old('token_url', $branchSetting->token_url ?: $branchSetting->tokenUrl())" />
                                            <p class="text-muted small mb-0">URL diisi otomatis saat environment diganti, kecuali sudah diubah manual.</p>
                                        </div>
                                    </details>

                                    <div class="d-flex flex-wrap gap-2">
                                        <x-button type="submit" icon="bx bx-save">Simpan pengaturan</x-button>
                                    </div>
                                </form>
                                <form method="POST" action="{{ route('integrations.satusehat.test') }}" class="mt-2" data-ss-remember-tab="pengaturan">
                                    @csrf
                                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                    <x-button type="submit" variant="info" icon="bx bx-plug">Tes Koneksi SATUSEHAT</x-button>
                                </form>
                            @else
                                <p class="mb-1">Status modul: <strong>{{ $settings->enabled ? 'Aktif' : 'Nonaktif' }}</strong></p>
                                <p class="mb-0 text-muted">Hanya pengelola integrasi yang dapat mengubah kredensial.</p>
                            @endif
                        </x-card>

                        <x-card title="Lokasi pelayanan">
                            @if ($canManage)
                                @if (! $branchSetting->connection_ok)
                                    <p class="text-muted mb-0">
                                        Klik <strong>Tes Koneksi SATUSEHAT</strong> terlebih dahulu untuk memuat lokasi dari SATUSEHAT.
                                    </p>
                                @else
                                    <form method="POST" action="{{ route('integrations.satusehat.location') }}" data-ss-remember-tab="pengaturan">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                        <input type="hidden" name="sync_enabled" value="0">

                                        @if ($choices === [])
                                            <div class="alert alert-warning py-2 px-3 small mb-3">
                                                Organization ini belum punya Location di SATUSEHAT (daftar kosong).
                                                Sync dari data cabang, atau isi Location ID manual.
                                            </div>
                                            @can('update', $branch)
                                                @if (auth()->user()?->can('integration.manage'))
                                                    <a href="{{ route('branches.show', $branch) }}" class="btn btn-info btn-sm mb-3">
                                                        <i class="bx bx-building-house me-1"></i> Sync lokasi dari Cabang
                                                    </a>
                                                @endif
                                            @endcan
                                            <x-input
                                                name="location_id"
                                                label="Location ID (manual)"
                                                :value="old('location_id', $branchSetting->location_id)"
                                                placeholder="UUID Location dari portal SATUSEHAT"
                                                required
                                            />
                                        @else
                                            <x-select
                                                name="location_id"
                                                label="Lokasi poli cabang"
                                                :options="$choices"
                                                :selected="old('location_id', $branchSetting->location_id)"
                                                placeholder="Pilih lokasi"
                                                required
                                            />
                                        @endif

                                        <p class="text-muted small">Ruangan poli/tindakan didaftarkan sebagai Location bertipe Room di bawah lokasi ini.</p>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" name="sync_enabled" id="ss-sync" value="1" @checked(old('sync_enabled', $branchSetting->sync_enabled))>
                                            <label class="form-check-label" for="ss-sync">Aktifkan sinkronisasi otomatis</label>
                                        </div>
                                        <x-button type="submit">Simpan lokasi</x-button>
                                    </form>
                                @endif
                            @else
                                <p class="mb-0">{{ $branchSetting->location_name ?: 'Lokasi belum dipetakan.' }}</p>
                            @endif
                        </x-card>

                        <x-card title="Catatan pengiriman">
                            <p class="text-muted small mb-0">
                                Status <strong>Terkirim lengkap</strong> berarti Encounter finished dan resource wajib skenario berhasil.
                                Status <strong>Sebagian terkirim</strong> berarti ada resource wajib yang gagal — Encounter belum ditutup; kirim ulang setelah diperbaiki.
                                OHIS, alergi, riwayat obat, golongan darah/rhesus/kehamilan, farmasi (KFA), dan rujukan dikirim hanya jika data lokal tersedia dan memenuhi syarat.
                                NIK serta rahasia API tidak disimpan plaintext di log.
                            </p>
                        </x-card>
                    </div>

                    <div class="col-xl-5">
                        @include('integrations.partials.satusehat-checklist', ['checklist' => $checklist])
                    </div>
                </div>
            </div>

            {{-- Tab Log --}}
            <div class="tab-pane fade" id="ss-tab-log">
                <x-card title="Log SATUSEHAT">
                    <form id="ss-log-filter" class="row g-2 align-items-end mb-3">
                        <div class="col-md-3">
                            <label class="form-label" for="ss-log-from">Dari</label>
                            <input id="ss-log-from" type="date" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="ss-log-to">Sampai</label>
                            <input id="ss-log-to" type="date" class="form-control">
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <x-button type="submit" variant="secondary" icon="bx bx-filter-alt">Filter</x-button>
                            <x-button type="button" variant="light" id="ss-log-reset">Reset</x-button>
                        </div>
                    </form>
                    <x-data-table
                        id="ss-logs-table"
                        :ajax="route('integrations.satusehat.logs')"
                        :columns="$logColumns"
                        :order="[[0, 'desc']]"
                        data-branch-id="{{ $branch?->id }}"
                    />
                </x-card>
            </div>
        </div>
    @endif

    <x-modal id="ss-log-modal" title="Hasil pengiriman" size="modal-xl">
        <div id="ss-log-body" class="text-muted">Memuat...</div>
    </x-modal>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var branchSelect = document.getElementById('ss-branch-id');
    if (branchSelect) {
        branchSelect.addEventListener('change', function () {
            document.getElementById('ss-branch-form')?.submit();
        });
    }

    var tabKey = 'ss-active-tab';
    var remembered = sessionStorage.getItem(tabKey);
    var openSettings = @json($openSettings ?? false);

    document.querySelectorAll('[data-ss-remember-tab]').forEach(function (form) {
        form.addEventListener('submit', function () {
            sessionStorage.setItem(tabKey, form.getAttribute('data-ss-remember-tab'));
        });
    });

    document.querySelectorAll('#ss-tabs [data-ss-tab]').forEach(function (btn) {
        btn.addEventListener('shown.bs.tab', function () {
            sessionStorage.setItem(tabKey, btn.getAttribute('data-ss-tab'));
            if (btn.getAttribute('data-ss-tab') === 'log' && window.jQuery) {
                var $table = window.jQuery('#ss-logs-table');
                if ($table.length && window.jQuery.fn.DataTable.isDataTable($table)) {
                    $table.DataTable().columns.adjust();
                }
            }
        });
    });

    if (! openSettings && remembered) {
        var target = document.querySelector('#ss-tabs [data-ss-tab="' + remembered + '"]');
        if (target && window.bootstrap) {
            window.bootstrap.Tab.getOrCreateInstance(target).show();
        }
    }

    document.getElementById('environment')?.addEventListener('change', function () {
        var urls = {
            sandbox: {
                base_url: 'https://api-satusehat-stg.dto.kemkes.go.id',
                token_url: 'https://api-satusehat-stg.dto.kemkes.go.id/oauth2/v1/accesstoken?grant_type=client_credentials'
            },
            production: {
                base_url: 'https://api-satusehat.kemkes.go.id',
                token_url: 'https://api-satusehat.kemkes.go.id/oauth2/v1/accesstoken?grant_type=client_credentials'
            }
        };
        var next = urls[this.value];
        if (! next) return;
        ['base_url', 'token_url'].forEach(function (name) {
            var input = document.getElementById(name);
            if (! input) return;
            var known = Object.values(urls).some(function (item) { return item[name] === input.value; });
            if (input.value === '' || known) input.value = next[name];
        });
    });

    var enableBox = document.getElementById('ss-enabled');
    var enableGaps = document.getElementById('ss-enable-gaps');
    var checklistGaps = @json(collect($checklist['sections'] ?? [])
        ->flatMap(fn ($section) => $section['items'] ?? [])
        ->filter(fn ($item) => ($item['required'] ?? true) && ($item['status'] ?? '') !== 'pass')
        ->map(fn ($item) => $item['label'] ?? '')
        ->filter()
        ->values()
        ->take(8)
        ->all());
    var readyToSync = @json((bool) ($checklist['summary']['ready_to_sync'] ?? false));

    function refreshEnableGaps() {
        if (! enableGaps) return;
        if (! enableBox?.checked) {
            enableGaps.classList.add('d-none');
            enableGaps.textContent = '';
            return;
        }
        if (readyToSync || checklistGaps.length === 0) {
            enableGaps.classList.add('d-none');
            enableGaps.textContent = '';
            return;
        }
        enableGaps.classList.remove('d-none');
        enableGaps.innerHTML = '<strong>Modul akan diaktifkan, tetapi belum siap sync.</strong> Masih kurang: '
            + checklistGaps.map(function (label) { return label; }).join('; ')
            + '. Lihat checklist di panel kanan.';
    }

    enableBox?.addEventListener('change', refreshEnableGaps);
    refreshEnableGaps();

    var table = document.getElementById('ss-logs-table');
    var filter = document.getElementById('ss-log-filter');
    if (table && filter && window.initServerDataTable) {
        var dt = window.initServerDataTable(table, {
            ajax: {
                url: table.dataset.ajax,
                data: function (payload) {
                    payload.branch_id = table.dataset.branchId || '';
                    payload.date_from = document.getElementById('ss-log-from').value;
                    payload.date_to = document.getElementById('ss-log-to').value;
                }
            }
        });

        filter.addEventListener('submit', function (event) {
            event.preventDefault();
            if (dt) dt.ajax.reload();
        });

        document.getElementById('ss-log-reset')?.addEventListener('click', function () {
            document.getElementById('ss-log-from').value = '';
            document.getElementById('ss-log-to').value = '';
            if (dt) dt.ajax.reload();
        });
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.js-ss-log');
        if (! button || ! window.axios || ! window.bootstrap) return;

        var body = document.getElementById('ss-log-body');
        var title = document.getElementById('ss-log-modalLabel');
        body.className = 'text-muted';
        body.textContent = 'Memuat...';
        if (title) title.textContent = button.dataset.title || 'Hasil pengiriman';
        window.bootstrap.Modal.getOrCreateInstance(document.getElementById('ss-log-modal')).show();

        window.axios.get(button.dataset.url).then(function (response) {
            body.className = '';
            body.innerHTML = response.data;
        }).catch(function () {
            body.className = 'text-danger';
            body.textContent = 'Detail pengiriman tidak bisa dimuat.';
        });
    });
});
</script>
@endpush
