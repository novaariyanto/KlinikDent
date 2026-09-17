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
</div>
