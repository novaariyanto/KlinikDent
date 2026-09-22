<div class="mb-4">
    <h3 class="mb-1">Cabang utama</h3>
    <p class="text-muted">Klinik dengan satu cabang tetap memakai cabang sebagai lokasi pelayanan.</p>
</div>

<form method="POST" action="{{ route('onboarding.step', 'branch') }}">
    @csrf
    <x-card>
        <div class="row">
            <div class="col-md-6">
                <x-input name="name" label="Nama Cabang" :value="$branch?->name ?? 'Cabang Utama'" required />
            </div>
            <div class="col-md-6">
                <x-input name="phone" label="Nomor Telepon" :value="$branch?->phone" />
            </div>
            <div class="col-12">
                <x-input name="address" label="Alamat" :value="$branch?->address ?? ($settings['address'] ?? '')" />
            </div>
            <div class="col-12">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $branch?->is_active ?? true))>
                    <label class="form-check-label" for="is_active">Status aktif</label>
                </div>
            </div>
        </div>
        @include('onboarding.partials.actions')
    </x-card>
</form>
