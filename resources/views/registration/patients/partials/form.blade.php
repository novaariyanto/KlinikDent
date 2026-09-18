@php
    $patient = $patient ?? null;
    $payerOptions = collect($payers ?? [])->mapWithKeys(fn ($payer) => [$payer->id => $payer->name])->all();
    $branchOptions = collect($branches ?? [])->mapWithKeys(fn ($branch) => [$branch->id => $branch->name])->all();
@endphp

<div class="row">
    <div class="col-md-6">
        <x-input name="name" label="Nama" :value="$patient?->name" required />
    </div>
    <div class="col-md-6">
        <x-input name="nik" label="NIK" :value="$patient?->nik" maxlength="16" />
    </div>
    <div class="col-md-4">
        <x-input name="dob" type="date" label="Tanggal Lahir" :value="optional($patient?->dob)->format('Y-m-d')" />
    </div>
    <div class="col-md-4">
        <x-select name="gender" label="Jenis Kelamin" :options="$genders ?? []" :selected="old('gender', $patient?->gender?->value)" />
    </div>
    <div class="col-md-4">
        <x-input name="phone" label="Telepon" :value="$patient?->phone" />
    </div>
    <div class="col-md-12">
        <x-input name="address" label="Alamat" :value="$patient?->address" />
    </div>
    <div class="col-md-6">
        <x-select name="default_payer_id" label="Penjamin Default" :options="$payerOptions" :selected="old('default_payer_id', $patient?->default_payer_id)" placeholder="Pilih penjamin" />
    </div>
    <div class="col-md-6">
        <x-input name="bpjs_number" label="No. Kartu BPJS" :value="$patient?->bpjs_number" />
    </div>
    @if (! $patient)
        <div class="col-md-6">
            <x-select name="branch_id" label="Cabang (untuk No. RM)" :options="$branchOptions" :selected="old('branch_id', auth()->user()?->branch_id)" required />
        </div>
    @endif
</div>
