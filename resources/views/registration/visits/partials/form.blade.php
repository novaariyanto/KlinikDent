@php
    $patient = $patient ?? null;
    $payerOptions = collect($payers ?? [])->mapWithKeys(fn ($payer) => [$payer->id => $payer->name])->all();
    $branchOptions = collect($branches ?? [])->mapWithKeys(fn ($branch) => [$branch->id => $branch->name])->all();
    $doctorOptions = collect($doctors ?? [])->mapWithKeys(fn ($doctor) => [$doctor->id => $doctor->name])->all();
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
        <x-input name="visit_date" type="date" label="Tanggal Kunjungan" :value="old('visit_date', now()->toDateString())" required />
    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label for="room_id" class="form-label">Poli / Ruangan</label>
            <select name="room_id" id="room_id" class="form-select @error('room_id') is-invalid @enderror">
                <option value="">Pilih poli</option>
                @foreach ($rooms ?? [] as $room)
                    <option
                        value="{{ $room->id }}"
                        data-branch="{{ $room->branch_id }}"
                        @selected((string) old('room_id') === (string) $room->id)
                    >
                        {{ $room->name }}{{ $room->type?->label() ? ' — '.$room->type->label() : '' }}
                    </option>
                @endforeach
            </select>
            @error('room_id')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <x-select name="doctor_id" label="Dokter" :options="$doctorOptions" :selected="old('doctor_id')" placeholder="Pilih dokter sesuai jadwal poli" />
        <p class="text-muted small" id="doctor-schedule-hint">Pilih cabang dan poli untuk melihat dokter yang praktik.</p>
    </div>
</div>
