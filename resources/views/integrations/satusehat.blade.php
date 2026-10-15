@extends('layouts.app')

@section('title', 'SATUSEHAT')

@section('content')
    <x-page-header
        title="Modul SATUSEHAT"
        :breadcrumb="['Integrasi' => route('integrations.index'), 'SATUSEHAT' => null]"
    />
    <x-alert />

    @if ($branches->isEmpty())
        <x-card title="Cabang">
            <p class="mb-0 text-muted">Klinik ini belum memiliki cabang.</p>
        </x-card>
    @else
        <x-card title="Cabang">
            <form method="GET" action="{{ route('integrations.satusehat') }}" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <x-select
                        name="branch_id"
                        label="Cabang yang dikonfigurasi"
                        :options="$branches->mapWithKeys(fn ($item) => [$item->id => $item->name])->all()"
                        :selected="$branch?->id"
                        placeholder=""
                    />
                </div>
                <div class="col-md-3">
                    <x-button type="submit" variant="secondary">Tampilkan</x-button>
                </div>
            </form>
        </x-card>

        <div class="row">
            <div class="col-md-3">
                <x-card title="Konfigurasi">
                    <p class="mb-1"><strong>{{ $branchSetting?->statusLabel() }}</strong></p>
                    <p class="mb-1">Mode: {{ $settings->fake ? 'Sandbox lokal' : ($branchSetting?->isProduction() ? 'Production' : 'Sandbox Kemenkes') }}</p>
                    <p class="mb-1">Organisasi: {{ $branchSetting?->organization_name ?: ($branchSetting?->organization_id ?: '-') }}</p>
                    <p class="mb-0">Lokasi: {{ $branchSetting?->location_name ?: ($branchSetting?->location_id ?: '-') }}</p>
                    @if ($branchSetting?->last_error)
                        <p class="text-danger small mt-2 mb-0">{{ $branchSetting->last_error }}</p>
                    @endif
                </x-card>
            </div>
            <div class="col-md-9">
                <div class="row">
                    <div class="col-md-3"><x-card title="Menunggu"><p class="h4 mb-0">{{ $stats['waiting'] }}</p></x-card></div>
                    <div class="col-md-3"><x-card title="Antrian"><p class="h4 mb-0">{{ $stats['queued'] }}</p></x-card></div>
                    <div class="col-md-3"><x-card title="Berhasil"><p class="h4 mb-0">{{ $stats['synced'] }}</p></x-card></div>
                    <div class="col-md-3"><x-card title="Gagal"><p class="h4 mb-0">{{ $stats['failed'] }}</p></x-card></div>
                </div>
            </div>
        </div>

        <x-card title="Kelengkapan data">
            <p class="mb-1">Dokter tanpa NIK: <strong>{{ $readiness['doctors_missing_nik'] }}</strong></p>
            <p class="mb-1">Pasien cabang ini tanpa NIK: <strong>{{ $readiness['patients_missing_nik'] }}</strong></p>
            <p class="mb-0 text-muted small">Sinkron terakhir: {{ $stats['last_synced_at'] ? \Illuminate\Support\Carbon::parse($stats['last_synced_at'])->timezone(config('app.timezone'))->format('d M Y H:i') : 'belum ada' }}. Ekstraoral, intraoral, gingiva, mukosa, gigi supernumerary, dan kebiasaan dikirim sebagai Kondisi Gigi dan Mulut Lainnya. OHIS, golongan darah, dan farmasi tidak dikirim.</p>
        </x-card>

        <div class="row">
            <div class="col-xl-6">
                <x-card title="Kredensial cabang">
                    @if ($canManage)
                        <form method="POST" action="{{ route('integrations.satusehat.update') }}">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                            <input type="hidden" name="enabled" value="0">
                            <input type="hidden" name="fake" value="0">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="enabled" id="ss-enabled" value="1" @checked(old('enabled', $settings->enabled))>
                                <label class="form-check-label" for="ss-enabled">Aktifkan modul SATUSEHAT</label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="fake" id="ss-fake" value="1" @checked(old('fake', $settings->fake))>
                                <label class="form-check-label" for="ss-fake">Mode sandbox lokal (tanpa API Kemenkes)</label>
                            </div>
                            <x-select
                                name="environment"
                                label="Environment"
                                :options="['sandbox' => 'Sandbox', 'production' => 'Production']"
                                :selected="old('environment', $branchSetting->environment ?: 'sandbox')"
                                placeholder=""
                                required
                            />
                            <x-input name="base_url" label="Base URL" :value="old('base_url', $branchSetting->base_url ?: $branchSetting->baseUrl())" />
                            <x-input name="token_url" label="Token URL" :value="old('token_url', $branchSetting->token_url ?: $branchSetting->tokenUrl())" />
                            <p class="text-muted small">Base URL dan Token URL dibaca dari isian ini. Ganti environment hanya mengisi ulang URL bawaan jika isian masih URL resmi SATUSEHAT.</p>
                            <x-input name="organization_id" label="Organization ID" :value="old('organization_id', $branchSetting->organization_id)" />
                            <script>
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
                                    if (!next) return;
                                    ['base_url', 'token_url'].forEach(function (name) {
                                        var input = document.getElementById(name);
                                        if (!input) return;
                                        var known = Object.values(urls).some(function (item) { return item[name] === input.value; });
                                        if (input.value === '' || known) input.value = next[name];
                                    });
                                });
                            </script>
                            <x-input name="client_id" label="Client ID" :value="old('client_id', $branchSetting->client_id)" />
                            <x-input name="client_secret" type="password" label="Client Secret" placeholder="{{ $branchSetting->hasSecret() ? 'Tersimpan — isi ulang untuk mengganti' : '' }}" autocomplete="new-password" />
                            @if ($branchSetting->secretIsUnreadable())
                                <p class="text-danger small">Client secret tersimpan tidak bisa dibuka. Isi ulang, lalu simpan.</p>
                            @endif
                            <x-button type="submit" icon="bx bx-save">Simpan pengaturan</x-button>
                        </form>
                        <form method="POST" action="{{ route('integrations.satusehat.test') }}" class="mt-3">
                            @csrf
                            <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                            <x-button type="submit" variant="info">Uji koneksi</x-button>
                        </form>
                    @else
                        <p class="mb-1">Status modul: <strong>{{ $settings->enabled ? 'Aktif' : 'Nonaktif' }}</strong></p>
                        <p class="mb-0 text-muted">Hanya pengelola integrasi yang dapat mengubah kredensial.</p>
                    @endif
                </x-card>
            </div>
            <div class="col-xl-6">
                <x-card title="Lokasi pelayanan">
                    @if ($canManage)
                        @php
                            $choices = $locationChoices;
                            if ($branchSetting->location_id && ! array_key_exists($branchSetting->location_id, $choices)) {
                                $choices[$branchSetting->location_id] = $branchSetting->location_name ?: $branchSetting->location_id;
                            }
                        @endphp
                        @if ($choices === [])
                            <p class="text-muted mb-0">Uji koneksi untuk memuat lokasi dari SATUSEHAT. Organization ID cabang adalah organisasi induk yang sudah terdaftar, tidak dibuat dari aplikasi.</p>
                        @else
                            <form method="POST" action="{{ route('integrations.satusehat.location') }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                                <input type="hidden" name="sync_enabled" value="0">
                                <x-select
                                    name="location_id"
                                    label="Lokasi poli cabang"
                                    :options="$choices"
                                    :selected="old('location_id', $branchSetting->location_id)"
                                    placeholder="Pilih lokasi"
                                    required
                                />
                                <p class="text-muted small">Ini lokasi induk cabang. Ruangan poli atau tindakan pada kunjungan didaftarkan sebagai Location bertipe Room di bawah lokasi ini.</p>
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="sync_enabled" id="ss-sync" value="1" @checked(old('sync_enabled', $branchSetting->sync_enabled)) @disabled(! $branchSetting->connection_ok)>
                                    <label class="form-check-label" for="ss-sync">Aktifkan sinkronisasi otomatis cabang ini</label>
                                </div>
                                <x-button type="submit">Simpan lokasi</x-button>
                            </form>
                        @endif
                    @else
                        <p class="mb-0">{{ $branchSetting->location_name ?: 'Lokasi belum dipetakan.' }}</p>
                    @endif
                </x-card>

                <x-card title="Kirim ulang kunjungan selesai">
                    @if (! $settings->enabled || ! $branchSetting->readyToSync())
                        <div class="alert alert-warning">Sinkronisasi cabang ini belum aktif.</div>
                    @endif
                    @can('satusehat.view')
                        <form method="POST" action="{{ route('integrations.satusehat.send') }}" class="row g-2">
                            @csrf
                            <div class="col-8">
                                <x-select
                                    name="visit_id"
                                    label="Kunjungan selesai"
                                    :options="$visits->mapWithKeys(fn ($visit) => [$visit->id => ($visit->patient?->name ?: 'Kunjungan').' · '.$visit->visit_date?->format('d M Y').' · '.($visit->satusehat_status?->label() ?: 'Belum dikirim')])->all()"
                                    :selected="old('visit_id')"
                                    placeholder="Pilih kunjungan"
                                    required
                                />
                            </div>
                            <div class="col-4 d-flex align-items-end">
                                @if ($settings->enabled && $branchSetting->readyToSync())
                                    <x-button type="submit">Kirim</x-button>
                                @else
                                    <x-button type="submit" disabled>Kirim</x-button>
                                @endif
                            </div>
                        </form>
                    @endcan
                </x-card>
            </div>
        </div>

        <x-card title="Kunjungan selesai cabang ini">
            <x-table>
                <thead>
                    <tr>
                        <th>Pasien</th>
                        <th>Tanggal</th>
                        <th>Status kirim</th>
                        <th>ID Encounter</th>
                        <th>Error</th>
                        <th></th>
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
                            <td>{{ $visit->satusehat_id ?: '-' }}</td>
                            <td class="text-muted">{{ \Illuminate\Support\Str::limit($visitErrors->get($visit->id)?->error_message, 80) ?: '-' }}</td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-soft-secondary js-ss-log"
                                        data-url="{{ route('integrations.satusehat.visits.logs', $visit) }}"
                                        data-title="Log Encounter {{ $visit->satusehat_id ?: '#'.$visit->id }}"
                                    >Log</button>
                                    @if ($settings->enabled && $branchSetting->readyToSync() && $visit->satusehat_status === \App\Enums\SatuSehatStatus::Failed)
                                        <form method="POST" action="{{ route('integrations.satusehat.send') }}">
                                            @csrf
                                            <input type="hidden" name="visit_id" value="{{ $visit->id }}">
                                            <x-button type="submit" variant="warning">Ulangi</x-button>
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
    @endif

    <x-card title="Log SATUSEHAT cabang ini">
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

    <x-modal id="ss-log-modal" title="Hasil pengiriman" size="modal-xl">
        <div id="ss-log-body" class="text-muted">Memuat...</div>
    </x-modal>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var table = document.getElementById('ss-logs-table');
    var filter = document.getElementById('ss-log-filter');
    if (!table || !filter || !window.initServerDataTable) return;

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

    document.getElementById('ss-log-reset').addEventListener('click', function () {
        document.getElementById('ss-log-from').value = '';
        document.getElementById('ss-log-to').value = '';
        if (dt) dt.ajax.reload();
    });

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.js-ss-log');
        if (!button || !window.axios || !window.bootstrap) return;

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
