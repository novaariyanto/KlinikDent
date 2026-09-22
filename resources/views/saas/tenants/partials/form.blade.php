@php
    $tenant = $tenant ?? null;
    $statusOptions = collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all();
@endphp

<div class="row">
    <div class="col-md-6">
        <x-input name="name" label="Nama Klinik" :value="$tenant?->name" required />
    </div>
    <div class="col-md-6">
        <x-input name="subdomain" label="Subdomain" :value="$tenant?->subdomain" required placeholder="klinik-a" />
        <small class="text-muted d-block mb-3 mt-n2">Huruf kecil, angka, dan tanda hubung. Harus unik.</small>
    </div>
    <div class="col-md-6">
        <x-select
            name="status"
            label="Status"
            :options="$statusOptions"
            :selected="old('status', $tenant?->status->value ?? 'trial')"
            required
        />
    </div>
    @if (($packages ?? collect())->isNotEmpty())
        <div class="col-md-6">
            <x-select
                name="package_id"
                label="Paket"
                :options="collect($packages)->mapWithKeys(fn ($package) => [$package->id => $package->name])->all()"
                :selected="old('package_id', $tenant?->plan_id)"
                placeholder="Tanpa paket"
            />
        </div>
    @endif
</div>

@if (! $tenant)
    <hr class="my-3">
    <h5 class="mb-3">Akun Owner Klinik</h5>
    <p class="text-muted small">Owner akan login dengan akun ini dan menyiapkan klinik melalui onboarding.</p>
    <div class="row">
        <div class="col-md-6">
            <x-input name="owner_name" label="Nama Owner" required />
        </div>
        <div class="col-md-6">
            <x-input name="owner_email" type="email" label="Email Owner" required />
        </div>
        <div class="col-md-6">
            <x-input name="owner_password" type="password" label="Password Owner" required autocomplete="new-password" />
        </div>
        <div class="col-md-6">
            <x-input name="owner_password_confirmation" type="password" label="Konfirmasi Password" required autocomplete="new-password" />
        </div>
    </div>
@endif
