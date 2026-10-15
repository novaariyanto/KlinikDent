@extends('layouts.app')

@section('title', 'Dokter & Tenaga Medis')

@section('content')
    <x-page-header title="Dokter & Tenaga Medis" :breadcrumb="['Tenaga Medis' => route('doctors.index')]" />
    <x-alert />
    <x-card title="Daftar Tenaga Medis">
        <x-slot:header-actions>
            @can('schedule.view')
                <x-button href="{{ route('doctors.schedule') }}" variant="secondary" icon="bx bx-calendar">Jadwal</x-button>
            @endcan
            @can('create', App\Models\Doctor::class)
                <x-button href="{{ route('doctors.create') }}" icon="bx bx-plus">Tambah Tenaga Medis</x-button>
            @endcan
        </x-slot:header-actions>
        <x-data-table id="doctors-table" :ajax="route('doctors.data')" :columns="$columns" :order="[[1, 'asc']]" />
    </x-card>

    <x-modal id="doctor-view-modal" title="Detail tenaga medis" size="modal-lg">
        <table class="table table-bordered align-middle mb-0">
            <tbody>
                <tr><th width="160">Nama</th><td id="doctor-view-name">-</td></tr>
                <tr><th>Email</th><td id="doctor-view-email">-</td></tr>
                <tr><th>Peran</th><td id="doctor-view-role">-</td></tr>
                <tr><th>Spesialisasi</th><td id="doctor-view-specialization">-</td></tr>
                <tr><th>SIP</th><td id="doctor-view-sip">-</td></tr>
                <tr><th>STR</th><td id="doctor-view-str">-</td></tr>
                <tr><th>NIK</th><td id="doctor-view-nik">-</td></tr>
                <tr><th>Telepon</th><td id="doctor-view-phone">-</td></tr>
                <tr><th>Cabang</th><td id="doctor-view-branch">-</td></tr>
                <tr>
                    <th>Status</th>
                    <td><span id="doctor-view-status" class="badge bg-secondary">-</span></td>
                </tr>
                <tr id="doctor-view-notes-row" class="d-none"><th>Catatan</th><td id="doctor-view-notes">-</td></tr>
            </tbody>
        </table>
        <div id="doctor-view-signature-wrap" class="mt-3 d-none">
            <div class="text-muted small mb-1">Tanda tangan</div>
            <img id="doctor-view-signature" alt="Tanda tangan" style="height:64px">
        </div>
        <x-slot:footer>
            <a id="doctor-view-account" href="#" class="btn btn-secondary waves-effect waves-light d-none">
                <i class="bx bx-user me-1"></i> Akun
            </a>
            <x-button type="button" variant="secondary" data-bs-dismiss="modal">Tutup</x-button>
        </x-slot:footer>
    </x-modal>

    @php
        $editDoctor = $editDoctor ?? null;
    @endphp
    <x-modal id="doctor-edit-modal" title="Edit {{ $editDoctor?->displayName() ?: 'tenaga medis' }}" size="modal-lg">
        <form
            id="doctor-edit-form"
            method="POST"
            action="{{ $editDoctor ? route('doctors.update', $editDoctor) : '#' }}"
            enctype="multipart/form-data"
            novalidate
        >
            @csrf
            @method('PUT')
            <input type="hidden" name="_return" value="index">
            <input type="hidden" name="_doctor_id" value="{{ old('_doctor_id', $editDoctor?->id) }}">

            <div class="row">
                <div class="col-md-6">
                    <x-input name="name" id="doctor-edit-name" label="Nama" :value="$editDoctor?->user?->name" required />
                </div>
                <div class="col-md-6">
                    <x-input name="specialization" id="doctor-edit-specialization" label="Spesialisasi" :value="$editDoctor?->specialization" placeholder="Dokter Gigi Umum" />
                </div>
                <div class="col-md-6">
                    <x-input name="sip" id="doctor-edit-sip" label="SIP" :value="$editDoctor?->sip" />
                </div>
                <div class="col-md-6">
                    <x-input name="str" id="doctor-edit-str" label="STR" :value="$editDoctor?->str" />
                </div>
                <div class="col-md-6">
                    <x-input name="nik" id="doctor-edit-nik" label="NIK" :value="$editDoctor?->nik" maxlength="16" inputmode="numeric" />
                </div>
                <div class="col-md-6">
                    <x-input name="phone" id="doctor-edit-phone" label="Telepon" :value="$editDoctor?->phone" />
                </div>
                <div class="col-12">
                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" id="doctor-edit-active" value="1" @checked(old('is_active', $editDoctor?->is_active ?? false))>
                        <label class="form-check-label" for="doctor-edit-active">Aktif praktik</label>
                    </div>
                </div>
                <div class="col-12">
                    <div class="mb-3">
                        <label class="form-label" for="doctor-edit-signature">Gambar tanda tangan</label>
                        <input class="form-control @error('signature') is-invalid @enderror" type="file" name="signature" id="doctor-edit-signature" accept="image/png,image/jpeg">
                        @error('signature')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        <img
                            id="doctor-edit-signature-preview"
                            alt="Tanda tangan"
                            class="mt-2 {{ $editDoctor?->signature_path ? '' : 'd-none' }}"
                            style="height:64px"
                            @if ($editDoctor?->signature_path) src="{{ asset('storage/'.$editDoctor->signature_path) }}" @endif
                        >
                    </div>
                </div>
                <div class="col-12">
                    <div class="mb-3">
                        <label for="doctor-edit-notes" class="form-label">Catatan</label>
                        <textarea name="notes" id="doctor-edit-notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $editDoctor?->notes) }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <x-button type="submit" icon="bx bx-save">Update</x-button>
                <x-button type="button" variant="secondary" data-bs-dismiss="modal">Batal</x-button>
            </div>
        </form>
    </x-modal>
@endsection

@push('scripts')
    <script>
        (function () {
            const viewModal = document.getElementById('doctor-view-modal');
            if (viewModal) {
                const textFields = ['name', 'email', 'specialization', 'sip', 'str', 'nik', 'phone', 'branch'];

                viewModal.addEventListener('show.bs.modal', function (event) {
                    const trigger = event.relatedTarget;
                    if (!trigger || !trigger.dataset.doctor) {
                        return;
                    }

                    const data = JSON.parse(trigger.dataset.doctor);

                    textFields.forEach(function (key) {
                        const cell = document.getElementById('doctor-view-' + key);
                        if (cell) {
                            cell.textContent = data[key] || '-';
                        }
                    });

                    const role = document.getElementById('doctor-view-role');
                    if (role) {
                        role.textContent = data.role || '-';
                    }

                    const status = document.getElementById('doctor-view-status');
                    if (status) {
                        status.textContent = data.is_active ? 'Aktif' : 'Nonaktif';
                        status.className = data.is_active ? 'badge bg-success' : 'badge bg-danger';
                    }

                    const notesRow = document.getElementById('doctor-view-notes-row');
                    const notes = document.getElementById('doctor-view-notes');
                    if (notesRow && notes) {
                        if (data.notes) {
                            notes.textContent = data.notes;
                            notesRow.classList.remove('d-none');
                        } else {
                            notes.textContent = '';
                            notesRow.classList.add('d-none');
                        }
                    }

                    const signatureWrap = document.getElementById('doctor-view-signature-wrap');
                    const signature = document.getElementById('doctor-view-signature');
                    if (signatureWrap && signature) {
                        if (data.signature) {
                            signature.src = data.signature;
                            signatureWrap.classList.remove('d-none');
                        } else {
                            signature.removeAttribute('src');
                            signatureWrap.classList.add('d-none');
                        }
                    }

                    const account = document.getElementById('doctor-view-account');
                    if (account) {
                        if (data.account_url) {
                            account.href = data.account_url;
                            account.classList.remove('d-none');
                        } else {
                            account.href = '#';
                            account.classList.add('d-none');
                        }
                    }

                    const title = document.getElementById('doctor-view-modalLabel');
                    if (title) {
                        title.textContent = data.name || 'Detail tenaga medis';
                    }
                });
            }

            const modal = document.getElementById('doctor-edit-modal');
            const form = document.getElementById('doctor-edit-form');
            if (!modal || !form) {
                return;
            }

            const fields = ['name', 'specialization', 'sip', 'str', 'nik', 'phone', 'notes'];

            modal.addEventListener('show.bs.modal', function (event) {
                const trigger = event.relatedTarget;
                if (!trigger || !trigger.dataset.doctor) {
                    return;
                }

                const data = JSON.parse(trigger.dataset.doctor);
                form.action = data.update_url;
                form.querySelector('[name="_doctor_id"]').value = data.id;

                fields.forEach(function (key) {
                    const input = form.querySelector('[name="' + key + '"]');
                    if (input) {
                        input.value = data[key] ?? '';
                    }
                });

                const active = form.querySelector('#doctor-edit-active');
                if (active) {
                    active.checked = !!data.is_active;
                }

                const file = form.querySelector('[name="signature"]');
                if (file) {
                    file.value = '';
                }

                form.querySelectorAll('.is-invalid').forEach(function (el) {
                    el.classList.remove('is-invalid');
                });
                form.querySelectorAll('.invalid-feedback').forEach(function (el) {
                    el.remove();
                });

                const preview = document.getElementById('doctor-edit-signature-preview');
                if (preview) {
                    if (data.signature) {
                        preview.src = data.signature;
                        preview.classList.remove('d-none');
                    } else {
                        preview.removeAttribute('src');
                        preview.classList.add('d-none');
                    }
                }

                const title = document.getElementById('doctor-edit-modalLabel');
                if (title) {
                    title.textContent = 'Edit ' + (data.name || 'tenaga medis');
                }
            });
        })();
    </script>
    @if ($errors->any() && old('_return') === 'index')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('doctor-edit-modal');
                if (modal && window.bootstrap) {
                    bootstrap.Modal.getOrCreateInstance(modal).show();
                }
            });
        </script>
    @endif
@endpush
