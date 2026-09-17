@php
    $branch = $branch ?? null;
    $tenantOptions = collect($tenants ?? [])->mapWithKeys(fn ($tenant) => [$tenant->id => $tenant->name])->all();
    $hours = old('opening_hours', $branch?->opening_hours ?? []);
@endphp

<div class="row">
    @if (auth()->user()?->isPlatformAdmin())
        <div class="col-md-6">
            <x-select
                name="tenant_id"
                label="Klinik"
                :options="$tenantOptions"
                :selected="old('tenant_id', $branch?->tenant_id)"
                required
            />
        </div>
    @endif
    <div class="col-md-6">
        <x-input name="name" label="Nama Cabang" :value="$branch?->name" required />
    </div>
    <div class="col-md-6">
        <x-input name="phone" label="Telepon" :value="$branch?->phone" />
    </div>
    <div class="col-md-12">
        <x-input name="address" label="Alamat" :value="$branch?->address" />
    </div>
    <div class="col-md-6">
        <div class="form-check form-switch mb-3 mt-2">
            <input
                class="form-check-input"
                type="checkbox"
                name="is_active"
                id="is_active"
                value="1"
                @checked(old('is_active', $branch?->is_active ?? true))
            >
            <label class="form-check-label" for="is_active">Aktif</label>
        </div>
    </div>
</div>

<h6 class="mt-2 mb-3">Jam Operasional</h6>
<div class="row">
    @foreach (\App\Models\Branch::WEEK_DAYS as $day => $label)
        <div class="col-md-6">
            <x-input
                name="opening_hours[{{ $day }}]"
                :label="$label"
                :value="$hours[$day] ?? ''"
                placeholder="08:00-17:00"
            />
        </div>
    @endforeach
</div>
