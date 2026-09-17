@php $medicine = $medicine ?? null; @endphp

<div class="row">
    <div class="col-md-6">
        <x-input name="name" label="Nama Obat" :value="$medicine?->name" required />
    </div>
    <div class="col-md-3">
        <x-select name="unit" label="Satuan" :options="\App\Models\Medicine::UNITS" :selected="old('unit', $medicine?->unit)" required />
    </div>
    <div class="col-md-3">
        <x-select name="category" label="Kategori" :options="\App\Models\Medicine::CATEGORIES" :selected="old('category', $medicine?->category)" required />
    </div>
    <div class="col-md-4">
        <x-input name="base_price" type="number" label="Harga Dasar" :value="$medicine?->base_price" required />
    </div>
    <div class="col-md-6">
        <div class="form-check form-switch mb-3 mt-4">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $medicine?->is_active ?? true))>
            <label class="form-check-label" for="is_active">Aktif</label>
        </div>
    </div>
</div>
