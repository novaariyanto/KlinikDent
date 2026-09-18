@extends('layouts.app')

@section('title', 'Integrasi Klinik')

@section('content')
    <x-page-header title="Integrasi Klinik" :breadcrumb="['Integrasi' => route('integrations.index')]" />
    <x-alert />

    <div class="row">
        @can('satusehat.view')
            <div class="col-lg-6">
                <x-card title="SATUSEHAT">
                    <p class="text-muted">Modul milik klinik ini. Kredensial dan sandbox diatur sendiri oleh owner, terpisah dari klinik lain.</p>
                    <p class="mb-3">
                        Status:
                        <strong>{{ $satusehat->enabled ? 'Aktif' : 'Nonaktif' }}</strong>
                        ·
                        {{ $satusehat->fake ? 'Sandbox lokal' : 'API SATUSEHAT' }}
                    </p>
                    <x-button href="{{ route('integrations.satusehat') }}" icon="bx bx-cloud">Buka modul SATUSEHAT</x-button>
                </x-card>
            </div>
        @endcan
        @can('bpjs.view')
            <div class="col-lg-6">
                <x-card title="BPJS VClaim">
                    <p class="text-muted">Cek kepesertaan dan klaim asuransi. Kredensial VClaim hanya untuk klinik ini.</p>
                    <p class="mb-3">
                        Status:
                        <strong>{{ $bpjs->enabled ? 'Aktif' : 'Nonaktif' }}</strong>
                        ·
                        {{ $bpjs->fake ? 'Sandbox lokal' : 'API VClaim' }}
                    </p>
                    <x-button href="{{ route('integrations.bpjs') }}" icon="bx bx-id-card">Buka modul BPJS</x-button>
                </x-card>
            </div>
        @endcan
    </div>
@endsection
