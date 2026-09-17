@php
    $procedure = $procedure ?? null;
    $serviceOptions = collect($services ?? [])->mapWithKeys(fn ($service) => [$service->id => $service->name])->all();
@endphp

<div class="row">
    <div class="col-md-4">
        <x-input name="code" label="Kode" :value="$procedure?->code" required />
    </div>
    <div class="col-md-8">
        <x-input name="name" label="Nama Tindakan" :value="$procedure?->name" required />
    </div>
    <div class="col-md-6">
        <x-select name="service_id" label="Layanan" :options="$serviceOptions" :selected="old('service_id', $procedure?->service_id)" placeholder="Tidak terkait layanan" />
    </div>
    <div class="col-md-6">
        <x-select name="category" label="Kategori" :options="\App\Models\Procedure::CATEGORIES" :selected="old('category', $procedure?->category)" required />
    </div>
    <div class="col-md-6">
        <div class="form-check form-switch mb-3 mt-2">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $procedure?->is_active ?? true))>
            <label class="form-check-label" for="is_active">Aktif</label>
        </div>
    </div>
</div>
