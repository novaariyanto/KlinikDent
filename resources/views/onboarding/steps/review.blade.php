<div class="mb-4">
    <h3 class="mb-1">Setup klinik Anda</h3>
    <p class="text-muted">Periksa ringkasan sebelum menyelesaikan onboarding.</p>
</div>

<x-card>
    <div class="row g-3">
        <div class="col-md-6"><strong>Tipe Klinik</strong><div>{{ $summary['template'] }}</div></div>
        <div class="col-md-6"><strong>Cabang</strong><div>{{ $summary['branches'] }}</div></div>
        <div class="col-md-6"><strong>Dokter</strong><div>{{ $summary['doctors'] }}</div></div>
        <div class="col-md-6"><strong>Staff</strong><div>{{ $summary['staff'] }}</div></div>
        <div class="col-md-6"><strong>Role aktif</strong><div>{{ $summary['roles'] }}</div></div>
        <div class="col-md-6"><strong>Layanan</strong><div>{{ $summary['services'] ?: 'Belum dikonfigurasi' }}</div></div>
        <div class="col-md-6"><strong>Obat</strong><div>{{ $summary['medicines'] ?: 'Belum dikonfigurasi' }}</div></div>
        <div class="col-md-6">
            <strong>Integrasi</strong>
            <div>
                SATUSEHAT: {{ $summary['satusehat'] ? 'Terkonfigurasi' : 'Dilewati (opsional)' }}<br>
                BPJS: {{ $summary['bpjs'] ? 'Terkonfigurasi' : 'Dilewati (opsional)' }}
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 justify-content-between mt-4">
        <form method="POST" action="{{ route('onboarding.back') }}">
            @csrf
            <x-button type="submit" variant="light" icon="bx bx-left-arrow-alt">Kembali</x-button>
        </form>
        <form method="POST" action="{{ route('onboarding.complete') }}">
            @csrf
            <x-button type="submit" icon="bx bx-check">Selesaikan Setup Klinik</x-button>
        </form>
    </div>
</x-card>
