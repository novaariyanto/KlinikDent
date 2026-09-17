@php
    $patient = $patient ?? null;
    $payerOptions = collect($payers ?? [])->mapWithKeys(fn ($payer) => [$payer->id => $payer->name])->all();
    $branchOptions = collect($branches ?? [])->mapWithKeys(fn ($branch) => [$branch->id => $branch->name])->all();
    $doctorOptions = collect($doctors ?? [])->mapWithKeys(fn ($doctor) => [$doctor->id => $doctor->name])->all();
    $roomOptions = collect($rooms ?? [])->mapWithKeys(fn ($room) => [$room->id => $room->name.($room->branch ? ' ('.$room->branch->name.')' : '')])->all();
@endphp

<div class="mb-4">
    <label class="form-label">Cari pasien</label>
    <input type="text" id="patient-search" class="form-control" placeholder="Nama, No. RM, NIK, atau telepon" autocomplete="off">
    <div id="patient-results" class="list-group mt-2 d-none"></div>
    <input type="hidden" name="patient_id" id="patient_id" value="{{ old('patient_id', $patient?->id) }}">
    <p class="text-muted mt-2 mb-0" id="selected-patient">
        @if ($patient)
            Terpilih: {{ $patient->medical_record_number }} — {{ $patient->name }}
        @else
            Kosongkan pencarian untuk membuat pasien baru.
        @endif
    </p>
</div>

<div id="new-patient-fields" class="@if($patient) d-none @endif">
    <h6 class="mb-3">Pasien Baru</h6>
    <div class="row">
        <div class="col-md-6"><x-input name="name" label="Nama" :value="$patient?->name" /></div>
        <div class="col-md-6"><x-input name="nik" label="NIK" :value="$patient?->nik" maxlength="16" /></div>
        <div class="col-md-4"><x-input name="dob" type="date" label="Tanggal Lahir" :value="optional($patient?->dob)->format('Y-m-d')" /></div>
        <div class="col-md-4"><x-select name="gender" label="Jenis Kelamin" :options="$genders ?? []" :selected="old('gender', $patient?->gender?->value)" /></div>
        <div class="col-md-4"><x-input name="phone" label="Telepon" :value="$patient?->phone" /></div>
        <div class="col-md-12"><x-input name="address" label="Alamat" :value="$patient?->address" /></div>
    </div>
</div>

<h6 class="mb-3 mt-4">Kunjungan</h6>
<div class="row">
    <div class="col-md-6">
        <x-select name="branch_id" label="Cabang" :options="$branchOptions" :selected="old('branch_id', $defaultBranchId)" required />
    </div>
    <div class="col-md-6">
        <x-select name="payer_id" label="Penjamin Kunjungan" :options="$payerOptions" :selected="old('payer_id', $patient?->default_payer_id)" required />
    </div>
    <div class="col-md-6">
        <x-select name="doctor_id" label="Dokter" :options="$doctorOptions" :selected="old('doctor_id')" placeholder="Pilih dokter" />
    </div>
    <div class="col-md-6">
        <x-select name="room_id" label="Poli / Ruangan" :options="$roomOptions" :selected="old('room_id')" placeholder="Pilih ruangan" />
    </div>
</div>
