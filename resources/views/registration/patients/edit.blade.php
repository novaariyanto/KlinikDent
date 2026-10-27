@extends('layouts.app')

@section('title', 'Edit Pasien')

@section('content')
    @php
        $branchOptions = collect($branches ?? [])->mapWithKeys(fn ($branch) => [$branch->id => $branch->name])->all();
        $defaultBranchId = old('branch_id', auth()->user()?->branch_id ?: array_key_first($branchOptions));
        $missingAddress = $patient->missingSatuSehatAddressFields();
    @endphp

    <x-page-header title="Edit Pasien" :breadcrumb="['Pasien' => route('registration.patients'), 'Edit' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Data Pasien">
                <form method="POST" action="{{ route('registration.patients.update', $patient) }}" id="patient-edit-form" novalidate>
                    @csrf
                    @method('PUT')
                    @include('registration.patients.partials.form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Update</x-button>
                        <x-button href="{{ route('registration.patients.show', $patient) }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
        <div class="col-lg-4">
            <x-card title="SATUSEHAT / IHS">
                <x-table>
                    <tbody>
                        <tr>
                            <th width="90">IHS</th>
                            <td id="patient-ihs-display">
                                @if (filled($patient->satusehat_id))
                                    <code>{{ $patient->satusehat_id }}</code>
                                @else
                                    <span class="text-muted">Belum ada</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>NIK</th>
                            <td>{{ $patient->nik ?: '-' }}</td>
                        </tr>
                    </tbody>
                </x-table>

                @can('update', $patient)
                    @if (filled($patient->nik) && ! filled($patient->satusehat_id))
                        <div class="mt-3">
                            <x-select
                                name="ihs_branch_id"
                                id="ihs_branch_id"
                                label="Cabang (kredensial SATUSEHAT)"
                                :options="$branchOptions"
                                :selected="$defaultBranchId"
                                placeholder="Pilih cabang"
                            />
                            <button type="button" class="btn btn-soft-info w-100" id="btn-lookup-patient-ihs">
                                <i class="bx bx-cloud-download me-1"></i> Cari IHS SATUSEHAT
                            </button>
                            <div id="patient-ihs-hint" class="form-text mt-2"></div>
                            <p class="text-muted small mt-2 mb-0">
                                Cari IHS cukup dengan NIK 16 digit. Alamat hanya dipakai jika NIK belum terdaftar di MPI.
                            </p>
                            @if ($missingAddress !== [])
                                <p class="text-warning small mt-2 mb-0">
                                    Belum lengkap: {{ implode(', ', $missingAddress) }}. Lengkapi di form lalu klik Cari IHS.
                                </p>
                            @endif
                        </div>
                    @elseif (! filled($patient->nik))
                        <p class="text-muted small mt-3 mb-0">Isi NIK untuk mencari / mendaftarkan IHS.</p>
                    @endif
                @endcan
            </x-card>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const ihsButton = document.getElementById('btn-lookup-patient-ihs');
        if (! ihsButton) return;

        const ihsDisplay = document.getElementById('patient-ihs-display');
        const ihsHint = document.getElementById('patient-ihs-hint');
        const branchSelect = document.getElementById('ihs_branch_id');
        const ihsUrl = @json(route('registration.patients.satusehat', $patient));
        const csrfToken = @json(csrf_token());

        function setIhsHint(text, isError) {
            if (! ihsHint) return;
            ihsHint.textContent = text || '';
            ihsHint.className = 'form-text mt-2' + (isError ? ' text-danger' : (text ? ' text-success' : ''));
        }

        function setIhsValue(ihs) {
            if (! ihsDisplay) return;
            if (ihs) {
                ihsDisplay.innerHTML = '<code></code>';
                ihsDisplay.querySelector('code').textContent = ihs;
            } else {
                ihsDisplay.innerHTML = '<span class="text-muted">Belum ada</span>';
            }
        }

        function fieldValue(name) {
            const el = document.getElementById(name) || document.querySelector('#patient-edit-form [name="' + name + '"]');
            return el ? String(el.value || '').trim() : '';
        }

        function collectPayload() {
            const payload = { branch_id: branchSelect ? branchSelect.value : '' };
            [
                'name', 'nik', 'dob', 'gender', 'address', 'address_city', 'address_postal_code',
                'province_code', 'city_code', 'district_code', 'village_code', 'rt', 'rw'
            ].forEach(function (name) {
                const val = fieldValue(name);
                if (val) payload[name] = val;
            });
            return payload;
        }

        ihsButton.addEventListener('click', function () {
            if (! branchSelect || ! branchSelect.value) {
                setIhsHint('Pilih cabang terlebih dahulu.', true);
                return;
            }

            const nik = fieldValue('nik').replace(/\D+/g, '');
            if (nik.length !== 16) {
                setIhsHint('NIK harus 16 digit.', true);
                return;
            }

            const payload = collectPayload();
            payload.nik = nik;

            ihsButton.disabled = true;
            setIhsHint('Mencari IHS di SATUSEHAT…', false);

            fetch(ihsUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            }).then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            }).then(function (result) {
                ihsButton.disabled = false;
                if (! result.ok) {
                    setIhsHint(result.data.message || 'Gagal mencari IHS.', true);
                    return;
                }
                setIhsValue(result.data.ihs || '');
                setIhsHint(result.data.message || 'IHS ditemukan.', false);
            }).catch(function () {
                ihsButton.disabled = false;
                setIhsHint('Gagal menghubungi server.', true);
            });
        });

        @if (session('error'))
            setIhsHint(@json(session('error')), true);
        @elseif (session('ihs_focus') || request()->boolean('ihs'))
            const nikNow = fieldValue('nik').replace(/\D+/g, '');
            setIhsHint(nikNow.length === 16 ? 'NIK 16 digit. Klik Cari IHS SATUSEHAT.' : 'NIK harus 16 digit.', nikNow.length !== 16);
        @endif
    })();
</script>
@endpush
