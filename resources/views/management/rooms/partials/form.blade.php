@php
    $room = $room ?? null;
    $branchOptions = collect($branches ?? [])->mapWithKeys(fn ($branch) => [$branch->id => $branch->name])->all();
@endphp

<div class="row">
    <div class="col-md-6">
        <x-select name="branch_id" label="Cabang" :options="$branchOptions" :selected="old('branch_id', $room?->branch_id)" required />
    </div>
    <div class="col-md-6">
        <x-input name="name" label="Nama Poli / Ruangan" :value="$room?->name" required />
    </div>
    <div class="col-md-6">
        <x-select name="type" label="Tipe" :options="\App\Enums\RoomType::options()" :selected="old('type', $room?->type?->value)" required />
    </div>
    <div class="col-md-6">
        <div class="form-check form-switch mb-3 mt-4">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $room?->is_active ?? true))>
            <label class="form-check-label" for="is_active">Aktif</label>
        </div>
    </div>
</div>
