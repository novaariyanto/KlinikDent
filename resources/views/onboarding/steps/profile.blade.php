<div class="mb-4">
    <h3 class="mb-1">Profil klinik</h3>
    <p class="text-muted">Lengkapi data klinik agar dokumen dan tampilan sudah sesuai.</p>
</div>

<form method="POST" action="{{ route('onboarding.step', 'profile') }}" enctype="multipart/form-data">
    @csrf
    <x-card>
        <div class="row">
            <div class="col-md-6">
                <x-input name="clinic_name" label="Nama Klinik" :value="$settings['clinic_name'] ?? $tenant->name" required />
            </div>
            <div class="col-md-6">
                <x-input name="rm_prefix" label="Nama singkat / kode klinik" :value="$settings['rm_prefix'] ?? ''" placeholder="KLINIK-A" />
            </div>
            <div class="col-12">
                <x-input name="address" label="Alamat" :value="$settings['address'] ?? ''" required />
            </div>
            <div class="col-md-6">
                <x-input name="phone" label="Nomor telepon" :value="$settings['phone'] ?? ''" />
            </div>
            <div class="col-md-6">
                <x-input name="email" type="email" label="Email" :value="$settings['email'] ?? ''" />
            </div>
            <div class="col-md-6">
                <x-input name="npwp" label="NPWP" :value="$settings['npwp'] ?? ''" />
            </div>
            <div class="col-md-6">
                <x-input name="license_number" label="Nomor izin klinik" :value="$settings['license_number'] ?? ''" />
            </div>
            <div class="col-12">
                <label class="form-label">Logo</label>
                @if ($hasCustomLogo)
                    <div class="mb-2"><img src="{{ $logoUrl }}" alt="Logo" style="max-height: 48px;"></div>
                @endif
                <input type="file" name="logo" class="form-control @error('logo') is-invalid @enderror" accept="image/*">
                @error('logo')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
        </div>
        @include('onboarding.partials.actions')
    </x-card>
</form>
