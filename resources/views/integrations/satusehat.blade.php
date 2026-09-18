@extends('layouts.app')

@section('title', 'SATUSEHAT')

@section('content')
    <x-page-header
        title="Modul SATUSEHAT"
        :breadcrumb="['Integrasi' => route('integrations.index'), 'SATUSEHAT' => null]"
    />
    <x-alert />

    <div class="row">
        <div class="col-xl-5">
            <x-card title="Pengaturan klinik">
                <p class="text-muted small">Kredensial ini hanya dipakai klinik Anda. Klinik lain tidak melihat atau memakai data ini.</p>
                @if ($canManage)
                    <form method="POST" action="{{ route('integrations.satusehat.update') }}">
                        @csrf
                        @method('PUT')
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
                        <x-input name="organization_id" label="Organization ID" :value="old('organization_id', $settings->credential('organization_id'))" />
                        <x-input name="client_id" label="Client ID" :value="old('client_id', $settings->credential('client_id'))" />
                        <x-input name="client_secret" type="password" label="Client Secret" placeholder="{{ $settings->hasCredential('client_secret') ? 'Tersimpan — isi ulang untuk mengganti' : '' }}" autocomplete="new-password" />
                        <x-input name="base_url" label="Base URL" :value="old('base_url', $settings->credential('base_url', config('integrations.satusehat.base_url')))" />
                        <x-input name="token_url" label="Token URL" :value="old('token_url', $settings->credential('token_url', config('integrations.satusehat.token_url')))" />
                        <x-button type="submit" icon="bx bx-save">Simpan pengaturan</x-button>
                    </form>
                @else
                    <p class="mb-1">Status: <strong>{{ $settings->enabled ? 'Aktif' : 'Nonaktif' }}</strong></p>
                    <p class="mb-0">Mode: <strong>{{ $settings->fake ? 'Sandbox lokal' : 'API' }}</strong></p>
                    <p class="text-muted small mt-2 mb-0">Hanya owner yang dapat mengubah kredensial.</p>
                @endif
            </x-card>
        </div>
        <div class="col-xl-7">
            <x-card title="Kirim kunjungan">
                @if (! $settings->enabled)
                    <div class="alert alert-warning">Modul nonaktif. Aktifkan dulu di pengaturan.</div>
                @endif
                @can('satusehat.view')
                    <form method="POST" action="{{ route('integrations.satusehat.send') }}" class="row g-2">
                        @csrf
                        <div class="col-8">
                            <x-select
                                name="visit_id"
                                label="Kunjungan"
                                :options="$visits->mapWithKeys(fn ($visit) => [$visit->id => ($visit->patient?->name ?: 'Kunjungan').' · '.$visit->visit_date?->format('d M Y')])->all()"
                                :selected="old('visit_id')"
                                required
                            />
                        </div>
                        <div class="col-4 d-flex align-items-end">
                            @if ($settings->enabled)
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

    <x-card title="Log SATUSEHAT klinik ini">
        @include('saas.partials.integration-logs', ['logs' => $logs])
    </x-card>
@endsection
