@extends('layouts.app')

@section('title', 'BPJS VClaim')

@section('content')
    <x-page-header
        title="Modul BPJS VClaim"
        :breadcrumb="['Integrasi' => route('integrations.index'), 'BPJS' => null]"
    />
    <x-alert />

    <div class="row">
        <div class="col-xl-5">
            <x-card title="Pengaturan klinik">
                <p class="text-muted small">Cons ID, secret, dan user key hanya untuk klinik Anda.</p>
                @if ($canManage)
                    <form method="POST" action="{{ route('integrations.bpjs.update') }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="enabled" value="0">
                        <input type="hidden" name="fake" value="0">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="enabled" id="bpjs-enabled" value="1" @checked(old('enabled', $settings->enabled))>
                            <label class="form-check-label" for="bpjs-enabled">Aktifkan modul BPJS</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="fake" id="bpjs-fake" value="1" @checked(old('fake', $settings->fake))>
                            <label class="form-check-label" for="bpjs-fake">Mode sandbox lokal (tanpa API VClaim)</label>
                        </div>
                        <x-input name="cons_id" label="Consumer ID" :value="old('cons_id', $settings->credential('cons_id'))" />
                        <x-input name="secret_key" type="password" label="Secret Key" placeholder="{{ $settings->hasCredential('secret_key') ? 'Tersimpan — isi ulang untuk mengganti' : '' }}" autocomplete="new-password" />
                        <x-input name="user_key" type="password" label="User Key" placeholder="{{ $settings->hasCredential('user_key') ? 'Tersimpan — isi ulang untuk mengganti' : '' }}" autocomplete="new-password" />
                        <x-input name="base_url" label="Base URL" :value="old('base_url', $settings->credential('base_url', config('integrations.bpjs.base_url')))" />
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
            <x-card title="Cek kepesertaan">
                @if (! $settings->enabled)
                    <div class="alert alert-warning">Modul nonaktif. Aktifkan dulu di pengaturan.</div>
                @endif
                @can('bpjs.view')
                    <form method="POST" action="{{ route('integrations.bpjs.check') }}" class="row g-2">
                        @csrf
                        <div class="col-6">
                            <x-select
                                name="patient_id"
                                label="Pasien"
                                :options="$patients->mapWithKeys(fn ($patient) => [$patient->id => $patient->name.' · '.($patient->nik ?: '-')])->all()"
                                :selected="old('patient_id')"
                                required
                            />
                        </div>
                        <div class="col-4">
                            <x-input name="bpjs_number" label="No. kartu (opsional)" :value="old('bpjs_number')" />
                        </div>
                        <div class="col-2 d-flex align-items-end">
                            @if ($settings->enabled)
                                <x-button type="submit">Cek</x-button>
                            @else
                                <x-button type="submit" disabled>Cek</x-button>
                            @endif
                        </div>
                    </form>
                @endcan
            </x-card>
        </div>
    </div>

    <x-card title="Klaim asuransi">
        @can('create', App\Models\InsuranceClaim::class)
            <form method="POST" action="{{ route('integrations.claims.store') }}" enctype="multipart/form-data" class="row g-2 mb-4">
                @csrf
                <div class="col-md-3">
                    <x-select name="patient_id" label="Pasien" :options="$patients->mapWithKeys(fn ($patient) => [$patient->id => $patient->name])->all()" :selected="old('patient_id')" required />
                </div>
                <div class="col-md-3">
                    <x-select name="payer_id" label="Asuransi" :options="$payers->mapWithKeys(fn ($payer) => [$payer->id => $payer->name])->all()" :selected="old('payer_id')" placeholder="Pilih" />
                </div>
                <div class="col-md-2">
                    <x-input name="amount" label="Nominal" type="number" :value="old('amount')" required />
                </div>
                <div class="col-md-2">
                    <x-input name="document" type="file" label="Dokumen" />
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <x-button type="submit" icon="bx bx-plus">Simpan klaim</x-button>
                </div>
            </form>
        @endcan

        <x-table>
            <thead>
                <tr>
                    <th>Pasien</th>
                    <th>Penjamin</th>
                    <th class="text-end">Nominal</th>
                    <th>Status</th>
                    <th>Dokumen</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($claims as $claim)
                    <tr>
                        <td>{{ $claim->patient?->name }}</td>
                        <td>{{ $claim->payer?->name ?: '-' }}</td>
                        <td class="text-end">{{ number_format((float) $claim->amount, 0, ',', '.') }}</td>
                        <td><span class="{{ $claim->status->badgeClass() }}">{{ $claim->status->label() }}</span></td>
                        <td>
                            @if ($claim->document_path)
                                <a href="{{ route('integrations.claims.download', $claim) }}">Unduh</a>
                            @else
                                -
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">Belum ada klaim.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    <x-card title="Log BPJS klinik ini">
        @include('saas.partials.integration-logs', ['logs' => $logs])
    </x-card>
@endsection
