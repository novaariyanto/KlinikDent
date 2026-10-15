@extends('layouts.app')

@section('title', 'Edit Tenaga Medis')

@section('content')
    @php
        $branchNames = $doctor->user?->branches->pluck('name') ?? collect();
        if ($branchNames->isEmpty() && $doctor->user?->branch) {
            $branchNames = collect([$doctor->user->branch->name]);
        }
        $active = (bool) old('is_active', $doctor->is_active);
    @endphp

    <x-page-header title="Edit {{ $doctor->displayName() }}" :breadcrumb="['Tenaga Medis' => route('doctors.index'), $doctor->displayName() => route('doctors.show', $doctor), 'Edit' => null]" />
    <x-alert />

    <div class="row">
        <div class="col-lg-8">
            <x-card title="Profil tenaga medis">
                <form method="POST" action="{{ route('doctors.update', $doctor) }}" enctype="multipart/form-data" novalidate>
                    @csrf
                    @method('PUT')

                    <div class="row">
                        <div class="col-md-6">
                            <x-input name="name" label="Nama" :value="$doctor->user?->name" required />
                        </div>
                        <div class="col-md-6">
                            <x-input name="specialization" label="Spesialisasi" :value="$doctor->specialization" placeholder="Dokter Gigi Umum" />
                        </div>
                        <div class="col-md-6">
                            <x-input name="sip" label="SIP" :value="$doctor->sip" />
                        </div>
                        <div class="col-md-6">
                            <x-input name="str" label="STR" :value="$doctor->str" />
                        </div>
                        <div class="col-md-6">
                            <x-input name="nik" label="NIK" :value="$doctor->nik" maxlength="16" inputmode="numeric" />
                        </div>
                        <div class="col-md-6">
                            <x-input name="phone" label="Telepon" :value="$doctor->phone" />
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch mb-3">
                                <input type="hidden" name="is_active" value="0">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked($active)>
                                <label class="form-check-label" for="is_active">Aktif praktik</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label class="form-label" for="signature">Gambar tanda tangan</label>
                                <input class="form-control @error('signature') is-invalid @enderror" type="file" name="signature" id="signature" accept="image/png,image/jpeg">
                                @error('signature')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                @if ($doctor->signature_path)
                                    <img src="{{ asset('storage/'.$doctor->signature_path) }}" alt="Tanda tangan" class="mt-2" style="height:64px">
                                @endif
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="notes" class="form-label">Catatan</label>
                                <textarea name="notes" id="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $doctor->notes) }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Update</x-button>
                        <x-button href="{{ route('doctors.show', $doctor) }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
        <div class="col-lg-4">
            <x-card title="Akun">
                <x-table>
                    <tbody>
                        <tr><th width="110">Email</th><td>{{ $doctor->user?->email ?: '-' }}</td></tr>
                        <tr><th>Peran</th><td>{{ $doctor->roleLabel() }}</td></tr>
                        <tr><th>Cabang</th><td>{{ $branchNames->implode(', ') ?: '-' }}</td></tr>
                    </tbody>
                </x-table>
            </x-card>
        </div>
    </div>
@endsection
