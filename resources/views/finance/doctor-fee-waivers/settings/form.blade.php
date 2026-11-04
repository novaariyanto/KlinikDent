@extends('layouts.app')

@php
    $isEdit = $setting->exists;
@endphp

@section('title', $isEdit ? 'Edit Kebijakan Pembebasan' : 'Tambah Kebijakan Pembebasan')

@section('content')
    <x-page-header
        :title="$isEdit ? 'Edit Kebijakan Pembebasan' : 'Tambah Kebijakan Pembebasan'"
        :breadcrumb="['Keuangan' => route('finance.index'), 'Kebijakan' => route('finance.doctor-fee-waivers.settings.index'), ($isEdit ? 'Edit' : 'Tambah') => null]"
    />
    <x-alert />

    <div class="row">
        <div class="col-lg-7">
            <x-card title="Form kebijakan">
                <form
                    method="POST"
                    action="{{ $isEdit ? route('finance.doctor-fee-waivers.settings.update', $setting) : route('finance.doctor-fee-waivers.settings.store') }}"
                    class="row g-3"
                >
                    @csrf
                    @if ($isEdit)
                        @method('PUT')
                    @endif

                    <div class="col-md-6">
                        <x-select
                            name="doctor_user_id"
                            label="Dokter"
                            :options="$doctors->mapWithKeys(fn ($d) => [$d->id => $d->name])->all()"
                            :selected="old('doctor_user_id', $setting->doctor_user_id)"
                            required
                        />
                    </div>
                    <div class="col-md-6">
                        <x-select
                            name="branch_id"
                            label="Cabang"
                            :options="$branches->mapWithKeys(fn ($b) => [$b->id => $b->name])->all()"
                            :selected="old('branch_id', $setting->branch_id)"
                            placeholder="Semua cabang"
                        />
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="is_allowed" id="is_allowed" value="1" @checked(old('is_allowed', $setting->is_allowed ?? true))>
                            <label class="form-check-label" for="is_allowed">Diizinkan membebaskan jasa</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="require_approval" id="require_approval" value="1" @checked(old('require_approval', $setting->require_approval))>
                            <label class="form-check-label" for="require_approval">Perlu persetujuan</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="include_waived_in_sharing_fee" id="include_waived_in_sharing_fee" value="1" @checked(old('include_waived_in_sharing_fee', $setting->include_waived_in_sharing_fee))>
                            <label class="form-check-label" for="include_waived_in_sharing_fee">Tetap hitung jasa dokter di sharing fee</label>
                            <div class="form-text">Jika tidak dicentang, jasa yang dibebaskan tidak masuk utang dokter.</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <x-input name="notes" label="Catatan" :value="old('notes', $setting->notes)" />
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Simpan</x-button>
                        <a href="{{ route('finance.doctor-fee-waivers.settings.index') }}" class="btn btn-light">Batal</a>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection
