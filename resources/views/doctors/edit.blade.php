@extends('layouts.app')

@section('title', 'Edit Tenaga Medis')

@section('content')
    @php
        $branchNames = $doctor->user?->branches->pluck('name') ?? collect();
        if ($branchNames->isEmpty() && $doctor->user?->branch) {
            $branchNames = collect([$doctor->user->branch->name]);
        }
        $active = filter_var(old('is_active', $doctor->is_active), FILTER_VALIDATE_BOOLEAN);
    @endphp

    <x-page-header title="Edit {{ $doctor->displayName() }}" :breadcrumb="['Tenaga Medis' => route('doctors.index'), 'Edit' => null]" />
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
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="is_active" class="form-label">Status <span class="text-danger">*</span></label>
                                <select name="is_active" id="is_active" class="form-select @error('is_active') is-invalid @enderror" required>
                                    <option value="1" @selected($active)>Aktif</option>
                                    <option value="0" @selected(! $active)>Nonaktif</option>
                                </select>
                                @error('is_active')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
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
                        <x-button href="{{ route('doctors.index') }}" variant="secondary">Cancel</x-button>
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
                        <tr>
                            <th>IHS</th>
                            <td>
                                @if (filled($doctor->satusehat_id))
                                    <code>{{ $doctor->satusehat_id }}</code>
                                @else
                                    <span class="text-muted">Belum ada</span>
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </x-table>
                @if (filled($doctor->nik) && ! filled($doctor->satusehat_id))
                    <form action="{{ route('doctors.satusehat', $doctor) }}" method="POST" class="mt-3">
                        @csrf
                        <x-button type="submit" variant="secondary" icon="bx bx-cloud-download">Ambil IHS</x-button>
                    </form>
                    <p class="text-muted small mt-2 mb-0">Mencari Practitioner SATUSEHAT berdasarkan NIK.</p>
                @elseif (! filled($doctor->nik))
                    <p class="text-muted small mt-3 mb-0">Isi NIK untuk mengambil IHS Practitioner.</p>
                @endif
            </x-card>
        </div>
    </div>
@endsection
