@php
    $patient = $patient ?? null;
    $payerOptions = collect($payers ?? [])->mapWithKeys(fn ($payer) => [$payer->id => $payer->name])->all();
    $branchOptions = collect($branches ?? [])->mapWithKeys(fn ($branch) => [$branch->id => $branch->name])->all();
@endphp

<div class="row">
    <div class="col-md-6">
        <x-input name="name" label="Nama lengkap (sesuai KTP)" :value="$patient?->name" required />
    </div>
    <div class="col-md-6">
        <x-input name="nik" id="nik" label="NIK" :value="$patient?->nik" maxlength="16" inputmode="numeric" autocomplete="off" />
        <div id="nik-wilayah-hint" class="form-text"></div>
    </div>
    <div class="col-md-4">
        <x-input name="dob" type="date" label="Tanggal Lahir" :value="optional($patient?->dob)->format('Y-m-d')" />
    </div>
    <div class="col-md-4">
        <x-select name="gender" label="Jenis Kelamin" :options="$genders ?? []" :selected="old('gender', $patient?->gender?->value)" />
    </div>
    <div class="col-md-4">
        <x-input name="birth_place" label="Tempat Lahir" :value="$patient?->birth_place" />
    </div>
    <div class="col-md-4">
        <x-select
            name="marital_status"
            label="Status Perkawinan"
            :options="$maritalStatuses ?? []"
            :selected="old('marital_status', $patient?->marital_status?->value)"
            placeholder="Pilih"
        />
    </div>
    <div class="col-md-4">
        <x-select
            name="citizenship"
            label="Kewarganegaraan"
            :options="$citizenships ?? []"
            :selected="old('citizenship', $patient?->citizenship?->value ?? 'WNI')"
            placeholder=""
        />
    </div>
    <div class="col-md-4">
        <x-input name="family_card_number" label="No. Kartu Keluarga" :value="$patient?->family_card_number" maxlength="16" />
    </div>
    <div class="col-md-4">
        <x-input name="phone" label="Telepon" :value="$patient?->phone" />
    </div>
    <div class="col-md-12">
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="marketing_consent" value="1" id="marketing_consent" @checked(old('marketing_consent', $patient?->marketing_consent))>
            <label class="form-check-label" for="marketing_consent">Setuju menerima informasi/promosi WhatsApp</label>
        </div>
    </div>

    <div class="col-12">
        <h6 class="mt-2 mb-2">Alamat KTP (SATUSEHAT)</h6>
        <p class="text-muted small mb-3">Pilih wilayah administratif sesuai KTP. Desa/kelurahan wajib untuk pendaftaran IHS pasien baru di MPI.</p>
    </div>
    <div class="col-md-12">
        <x-input name="address" label="Alamat (jalan / dusun)" :value="$patient?->address" />
    </div>
    <div class="col-md-6">
        <x-select
            name="province_code"
            id="province_code"
            class="js-wilayah-select"
            label="Provinsi"
            :options="$provinces ?? []"
            :selected="old('province_code', $patient?->province_code)"
            placeholder="Cari / pilih provinsi"
            data-placeholder="Cari / pilih provinsi"
        />
    </div>
    <div class="col-md-6">
        <x-select
            name="city_code"
            id="city_code"
            class="js-wilayah-select"
            label="Kabupaten / Kota"
            :options="$cities ?? []"
            :selected="old('city_code', $patient?->city_code)"
            placeholder="Cari / pilih kab/kota"
            data-placeholder="Cari / pilih kab/kota"
        />
    </div>
    <div class="col-md-6">
        <x-select
            name="district_code"
            id="district_code"
            class="js-wilayah-select"
            label="Kecamatan"
            :options="$districts ?? []"
            :selected="old('district_code', $patient?->district_code)"
            placeholder="Cari / pilih kecamatan"
            data-placeholder="Cari / pilih kecamatan"
        />
    </div>
    <div class="col-md-6">
        <x-select
            name="village_code"
            id="village_code"
            class="js-wilayah-select"
            label="Desa / Kelurahan (wajib IHS)"
            :options="$villages ?? []"
            :selected="old('village_code', $patient?->village_code)"
            placeholder="Cari / pilih desa/kelurahan"
            data-placeholder="Cari / pilih desa/kelurahan"
        />
        <div class="form-text">Pilih provinsi sampai desa/kelurahan sesuai KTP.</div>
    </div>
    <div class="col-md-4">
        <x-input name="address_city" id="address_city" label="Kota (nama)" :value="$patient?->address_city" />
    </div>
    <div class="col-md-4">
        <x-input name="address_postal_code" id="address_postal_code" label="Kode Pos" :value="$patient?->address_postal_code" maxlength="10" />
    </div>
    <div class="col-md-2">
        <x-input name="rt" label="RT" :value="$patient?->rt" maxlength="5" />
    </div>
    <div class="col-md-2">
        <x-input name="rw" label="RW" :value="$patient?->rw" maxlength="5" />
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

@include('registration.partials.wilayah-nik-scripts')
