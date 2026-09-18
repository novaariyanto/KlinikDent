@php
    $doctor = $doctor ?? null;
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
        <x-input name="phone" label="Telepon" :value="$doctor?->phone" />
    </div>
    <div class="col-md-6">
        <div class="form-check form-switch mb-3 mt-4">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $doctor?->is_active ?? true))>
            <label class="form-check-label" for="is_active">Aktif praktik</label>
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
