@php
    $service = $service ?? null;
@endphp

<div class="row">
    <div class="col-md-6">
        <x-input name="name" label="Nama Layanan" :value="$service?->name" required />
    </div>
    <div class="col-md-6">
        <x-select
            name="category"
            label="Kategori"
            :options="\App\Models\Service::CATEGORIES"
            :selected="old('category', $service?->category)"
            required
        />
    </div>
    <div class="col-md-6">
        <div class="form-check form-switch mb-3 mt-2">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                @checked(old('is_active', $service?->is_active ?? true))>
            <label class="form-check-label" for="is_active">Aktif</label>
        </div>
    </div>
</div>
