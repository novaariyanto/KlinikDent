@php
    $medicineName = old('name', $medicine?->name);
    $kfaBrowserUrl = 'https://satusehat.kemkes.go.id/kfa-browser/';
    $kfaDocsUrl = 'https://satusehat.kemkes.go.id/platform/docs/id/master-data/kfa/rest-api-kfa/';
@endphp

<x-card title="Playbook: isi kode KFA">
    <p class="text-muted small mb-3">
        Kode KFA (Kamus Farmasi &amp; Alat Kesehatan) dipakai saat resep dikirim ke SATUSEHAT
        sebagai <code>Medication</code> / <code>MedicationRequest</code>. Tanpa kode ini, item obat dilewati.
    </p>

    <ol class="ps-3 mb-3 small">
        <li class="mb-2">
            Buka
            <a href="{{ $kfaBrowserUrl }}" target="_blank" rel="noopener noreferrer">KFA Browser SATUSEHAT</a>
            (portal resmi Kemenkes).
        </li>
        <li class="mb-2">
            Cari nama generik / merek / kekuatan yang sama dengan obat di klinik
            @if (filled($medicineName))
                (contoh: <strong>{{ $medicineName }}</strong>).
            @else
                (sesuai nama yang Anda isi di form).
            @endif
        </li>
        <li class="mb-2">
            Buka detail produk. Catat <strong>KFA Code</strong> pada baris yang sesuai kemasan/unit yang Anda jual.
        </li>
        <li class="mb-2">
            Tempel kode ke kolom <strong>Kode KFA</strong> di form (hanya angka, tanpa spasi).
        </li>
        <li class="mb-0">
            Simpan obat. Saat resep berstatus terkirim/dipenuhi, item dengan KFA ikut disinkronkan.
        </li>
    </ol>

    <div class="alert alert-info py-2 px-3 small mb-3">
        <strong>Hierarki kode (singkat):</strong>
        <ul class="mb-0 ps-3 mt-1">
            <li><strong>92…</strong> POV — produk virtual / template</li>
            <li><strong>93…</strong> POA — produk aktual (merek + sediaan)</li>
            <li><strong>94…</strong> POAK — kemasan (paling spesifik untuk unit jual)</li>
        </ul>
        Utamakan kode yang paling cocok dengan sediaan &amp; kemasan di klinik (seringnya POA/POAK).
    </div>

    <div class="alert alert-warning py-2 px-3 small mb-3">
        Jangan mengarang kode. Salah kode = Medication ditolak atau salah produk di SATUSEHAT.
        Satu tenant tidak boleh memakai KFA yang sama untuk dua obat berbeda.
    </div>

    <div class="d-grid gap-2">
        <a
            href="{{ $kfaBrowserUrl }}"
            class="btn btn-sm btn-info"
            target="_blank"
            rel="noopener noreferrer"
        >
            <i class="bx bx-search-alt me-1"></i> Buka KFA Browser
        </a>
        <a
            href="{{ $kfaDocsUrl }}"
            class="btn btn-sm btn-soft-secondary"
            target="_blank"
            rel="noopener noreferrer"
        >
            <i class="bx bx-book-open me-1"></i> Dokumentasi API KFA
        </a>
        <button type="button" class="btn btn-sm btn-soft-primary" id="kfa-fill-hint" data-medicine-name="{{ e($medicineName ?? '') }}">
            <i class="bx bx-copy me-1"></i> Salin nama obat untuk pencarian
        </button>
    </div>

    <details class="mt-3">
        <summary class="small text-muted" style="cursor: pointer">Contoh alur singkat</summary>
        <div class="small text-muted mt-2">
            Nama di form: <em>Amoxicillin 500mg</em> → cari “Amoxicillin 500” di KFA Browser →
            pilih kapsul 500 mg yang sesuai → salin KFA Code (mis. 93xxxxxxx) → tempel ke form → Update.
        </div>
    </details>
</x-card>

@once
    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('kfa-fill-hint');
        if (! btn) return;
        btn.addEventListener('click', function () {
            var nameInput = document.getElementById('name');
            var text = (nameInput && nameInput.value.trim()) || btn.getAttribute('data-medicine-name') || '';
            if (! text) {
                window.alert('Isi nama obat terlebih dahulu.');
                return;
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () {
                    btn.innerHTML = '<i class="bx bx-check me-1"></i> Nama tersalin — tempel di KFA Browser';
                    setTimeout(function () {
                        btn.innerHTML = '<i class="bx bx-copy me-1"></i> Salin nama obat untuk pencarian';
                    }, 2500);
                }).catch(function () {
                    window.prompt('Salin nama ini, lalu tempel di KFA Browser:', text);
                });
            } else {
                window.prompt('Salin nama ini, lalu tempel di KFA Browser:', text);
            }
        });
    });
    </script>
    @endpush
@endonce
