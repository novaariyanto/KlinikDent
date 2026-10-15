@php
    $doctor = $doctor ?? null;
    $isActive = filter_var(old('is_active', $doctor?->is_active ?? true), FILTER_VALIDATE_BOOLEAN);
@endphp

<div class="row">
    @if ($doctor)
        <div class="col-md-6">
            <x-input name="name" label="Nama" :value="$doctor->user?->name" required />
        </div>
    @endif
    <div class="col-md-6">
        <x-input name="specialization" label="Spesialisasi" :value="$doctor?->specialization" placeholder="Dokter Gigi Umum" />
    </div>
    <div class="col-md-6">
        <x-input name="sip" label="SIP" :value="$doctor?->sip" />
    </div>
    <div class="col-md-6">
        <x-input name="str" label="STR" :value="$doctor?->str" />
    </div>
    <div class="col-md-6">
        <x-input name="nik" label="NIK SATUSEHAT" :value="$doctor?->nik" maxlength="16" inputmode="numeric" />
    </div>
    <div class="col-md-6">
        <x-input name="phone" label="Telepon" :value="$doctor?->phone" />
    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label for="is_active" class="form-label">Status <span class="text-danger">*</span></label>
            <select name="is_active" id="is_active" class="form-select @error('is_active') is-invalid @enderror" required>
                <option value="1" @selected($isActive)>Aktif</option>
                <option value="0" @selected(! $isActive)>Nonaktif</option>
            </select>
            @error('is_active')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-12">
        <div class="mb-3">
            <label class="form-label" for="signature">Gambar tanda tangan</label>
            <input class="form-control" type="file" name="signature" id="signature" accept="image/png,image/jpeg">
            @if ($doctor?->signature_path)
                <img src="{{ asset('storage/'.$doctor->signature_path) }}" alt="Tanda tangan" class="mt-2" style="height:64px">
            @endif
        </div>
    </div>
    <div class="col-12">
        <div class="mb-3">
            <label for="notes" class="form-label">Catatan</label>
            <textarea name="notes" id="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $doctor?->notes) }}</textarea>
            @error('notes')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>
