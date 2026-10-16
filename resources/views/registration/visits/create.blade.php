@extends('layouts.app')

@section('title', 'Pendaftaran Baru')

@section('content')
    <x-page-header title="Pendaftaran Baru" :breadcrumb="['Pendaftaran' => route('registration.index'), 'Baru' => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Form Pendaftaran">
                <form method="POST" action="{{ route('registration.visits.store') }}" novalidate>
                    @csrf
                    @include('registration.visits.partials.form')
                    <div class="d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Daftarkan</x-button>
                        <x-button href="{{ route('registration.index') }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        const input = document.getElementById('patient-search');
        const results = document.getElementById('patient-results');
        const patientId = document.getElementById('patient_id');
        const satusehatId = document.getElementById('satusehat_id');
        const selected = document.getElementById('selected-patient');
        const newFields = document.getElementById('new-patient-fields');
        const ihsDisplay = document.getElementById('patient-ihs-display');
        const ihsHint = document.getElementById('patient-ihs-hint');
        const ihsButton = document.getElementById('btn-lookup-patient-ihs');
        const nikInput = document.getElementById('nik');
        const searchUrl = @json(route('registration.patients.search'));
        const ihsUrl = @json(route('registration.patient-ihs'));
        const csrfToken = @json(csrf_token());
        let timer = null;
        let ihsRequestToken = 0;
        let pendingVillageCode = '';

        function setIhsHint(text, isError) {
            if (! ihsHint) return;
            ihsHint.textContent = text || '';
            ihsHint.className = 'form-text' + (isError ? ' text-danger' : (text ? ' text-success' : ''));
        }

        function setIhsValue(ihs) {
            if (satusehatId) satusehatId.value = ihs || '';
            if (! ihsDisplay) return;
            if (ihs) {
                ihsDisplay.innerHTML = 'IHS: <code></code>';
                ihsDisplay.querySelector('code').textContent = ihs;
                ihsDisplay.classList.remove('text-muted');
            } else {
                ihsDisplay.textContent = 'IHS belum dicari';
                ihsDisplay.classList.add('text-muted');
            }
        }

        function fieldEl(name) {
            return document.getElementById(name) || document.querySelector('[name="' + name + '"]');
        }

        function setFieldValue(name, value) {
            const el = fieldEl(name);
            if (! el || value === undefined || value === null || value === '') return;
            el.value = value;
            if (window.jQuery && window.jQuery(el).data('select2')) {
                window.jQuery(el).val(value).trigger('change');
            }
        }

        function clearSelectedPatient() {
            if (patientId) patientId.value = '';
            setIhsValue('');
            setIhsHint('', false);
            pendingVillageCode = '';
            if (selected) selected.textContent = 'Kosongkan pencarian untuk membuat pasien baru.';
            if (newFields) newFields.classList.remove('d-none');
        }

        function collectWilayahPayload(payload) {
            const fields = [
                'name', 'nik', 'dob', 'gender', 'address', 'address_city', 'address_postal_code',
                'province_code', 'city_code', 'district_code', 'village_code', 'rt', 'rw'
            ];
            fields.forEach(function (name) {
                const el = fieldEl(name);
                if (! el) return;
                const val = String(el.value || '').trim();
                if (val) payload[name] = val;
            });
            return payload;
        }

        function fillPatientForm(row) {
            setFieldValue('name', row.name);
            setFieldValue('nik', row.nik);
            setFieldValue('dob', row.dob);
            setFieldValue('gender', row.gender);
            setFieldValue('phone', row.phone);
            setFieldValue('address', row.address);
            setFieldValue('address_city', row.address_city);
            setFieldValue('address_postal_code', row.address_postal_code);
            setFieldValue('rt', row.rt);
            setFieldValue('rw', row.rw);
            pendingVillageCode = row.village_code || '';

            if (row.nik && String(row.nik).replace(/\D+/g, '').length === 16 && nikInput) {
                nikInput.dispatchEvent(new Event('input', { bubbles: true }));
            } else {
                setFieldValue('province_code', row.province_code);
                setFieldValue('city_code', row.city_code);
                setFieldValue('district_code', row.district_code);
                setFieldValue('village_code', row.village_code);
            }
        }

        function lookupPatientIhs(options) {
            options = options || {};
            const silentMissing = !! options.silentMissing;
            const branchSelect = document.getElementById('branch_id');
            const branchId = branchSelect ? branchSelect.value : '';

            if (! branchId) {
                if (! silentMissing) setIhsHint('Pilih cabang terlebih dahulu.', true);
                return;
            }

            if (satusehatId && satusehatId.value) {
                setIhsValue(satusehatId.value);
                setIhsHint('IHS sudah tersedia.', false);
                return;
            }

            const payload = collectWilayahPayload({ branch_id: branchId });
            if (patientId && patientId.value) {
                payload.patient_id = patientId.value;
            } else {
                const nik = String(nikInput && nikInput.value || '').replace(/\D+/g, '');
                if (nik.length !== 16) {
                    if (! silentMissing) setIhsHint('Isi NIK 16 digit pada form pasien baru, atau pilih pasien lokal.', true);
                    return;
                }
                payload.nik = nik;
            }

            const isNewPatient = ! (patientId && patientId.value);
            if (isNewPatient && ! payload.village_code) {
                // Coba cari dulu di MPI; bila belum ada, user lengkapi desa/RT/RW.
            }

            const token = ++ihsRequestToken;
            if (ihsButton) ihsButton.disabled = true;
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
                if (token !== ihsRequestToken) return;
                if (ihsButton) ihsButton.disabled = false;
                if (! result.ok) {
                    const msg = result.data.message || 'Gagal mencari IHS.';
                    if (/desa|kelurahan|wilayah|RT|RW/i.test(msg) && newFields) {
                        newFields.classList.remove('d-none');
                    }
                    if (silentMissing && /tidak ditemukan di MPI/i.test(msg) && /membutuhkan/i.test(msg)) {
                        setIhsHint('NIK belum ada di MPI. Lengkapi desa/kelurahan, RT, RW lalu IHS akan dicari ulang otomatis / klik Cari IHS.', true);
                        return;
                    }
                    setIhsHint(msg, true);
                    return;
                }
                setIhsValue(result.data.ihs || '');
                setIhsHint(result.data.message || 'IHS ditemukan.', false);
                if (result.data.patient_id && patientId && ! patientId.value) {
                    patientId.value = result.data.patient_id;
                    if (selected && result.data.medical_record_number) {
                        selected.textContent = 'Terpilih: ' + result.data.medical_record_number + ' — ' + (result.data.name || '');
                        if (newFields) newFields.classList.add('d-none');
                    }
                }
            }).catch(function () {
                if (token !== ihsRequestToken) return;
                if (ihsButton) ihsButton.disabled = false;
                setIhsHint('Gagal menghubungi server.', true);
            });
        }

        if (input) {
            input.addEventListener('input', function () {
                clearTimeout(timer);
                const q = input.value.trim();
                if (q.length < 2) {
                    results.classList.add('d-none');
                    results.innerHTML = '';
                    if (q.length === 0) clearSelectedPatient();
                    return;
                }
                timer = setTimeout(function () {
                    fetch(searchUrl + '?q=' + encodeURIComponent(q), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(r => r.json()).then(function (rows) {
                        results.innerHTML = '';
                        if (!rows.length) {
                            results.classList.add('d-none');
                            return;
                        }
                        rows.forEach(function (row) {
                            const a = document.createElement('a');
                            a.href = '#';
                            a.className = 'list-group-item list-group-item-action';
                            a.textContent = row.medical_record_number + ' — ' + row.name + (row.satusehat_id ? ' · IHS ' + row.satusehat_id : '');
                            a.addEventListener('click', function (e) {
                                e.preventDefault();
                                patientId.value = row.id;
                                selected.textContent = 'Terpilih: ' + row.medical_record_number + ' — ' + row.name;
                                setIhsValue(row.satusehat_id || '');
                                if (row.default_payer_id) {
                                    const payer = document.getElementById('payer_id');
                                    if (payer) payer.value = row.default_payer_id;
                                }
                                if (row.satusehat_id) {
                                    newFields.classList.add('d-none');
                                    setIhsHint('IHS dari data lokal.', false);
                                } else {
                                    newFields.classList.remove('d-none');
                                    fillPatientForm(row);
                                    setIhsHint('Mencari IHS pasien di SATUSEHAT…', false);
                                    lookupPatientIhs({ silentMissing: true });
                                }
                                results.classList.add('d-none');
                            });
                            results.appendChild(a);
                        });
                        results.classList.remove('d-none');
                    });
                }, 250);
            });
        }

        if (ihsButton) {
            ihsButton.addEventListener('click', function () {
                lookupPatientIhs({ silentMissing: false });
            });
        }

        document.addEventListener('wilayah:nik-synced', function () {
            if (pendingVillageCode) {
                setFieldValue('village_code', pendingVillageCode);
                pendingVillageCode = '';
            }

            if (satusehatId && satusehatId.value) return;
            lookupPatientIhs({ silentMissing: true });
        });

        ['village_code', 'rt', 'rw'].forEach(function (name) {
            const el = fieldEl(name);
            if (! el) return;
            const handler = function () {
                if (satusehatId && satusehatId.value) return;
                const village = String(fieldEl('village_code')?.value || '').trim();
                const rt = String(fieldEl('rt')?.value || '').trim();
                const rw = String(fieldEl('rw')?.value || '').trim();
                if (village && rt && rw) {
                    lookupPatientIhs({ silentMissing: true });
                }
            };
            el.addEventListener('change', handler);
            el.addEventListener('blur', handler);
            if (window.jQuery && (name === 'village_code')) {
                window.jQuery(el).on('change', handler);
            }
        });

        // Pasien pra-terpilih dari query (?patient_id=) tanpa IHS → auto cari.
        @if ($patient && filled($patient->nik) && ! filled($patient->satusehat_id))
            lookupPatientIhs({ silentMissing: true });
        @elseif ($patient && filled($patient->satusehat_id))
            setIhsHint('IHS dari data lokal.', false);
        @endif

        const branchSelect = document.getElementById('branch_id');
        const roomSelect = document.getElementById('room_id');
        const doctorSelect = document.getElementById('doctor_id');
        const dateInput = document.getElementById('visit_date');
        const hint = document.getElementById('doctor-schedule-hint');
        const availabilityUrl = @json(route('registration.availability'));
        const fallbackDoctors = @json(collect($doctors ?? [])->map(fn ($doctor) => ['id' => $doctor->id, 'name' => $doctor->name])->values());
        const preferredDoctor = @json((string) old('doctor_id', ''));

        function filterRooms() {
            if (!branchSelect || !roomSelect) return;
            const branchId = branchSelect.value;
            let keep = false;
            roomSelect.querySelectorAll('option[data-branch]').forEach(function (opt) {
                const show = !branchId || opt.getAttribute('data-branch') === branchId;
                opt.hidden = !show;
                opt.disabled = !show;
                if (show && opt.value === roomSelect.value) keep = true;
            });
            if (!keep) roomSelect.value = '';
        }

        function fillDoctors(list, fromSchedule) {
            if (!doctorSelect) return;
            const current = doctorSelect.value || preferredDoctor;
            doctorSelect.innerHTML = '<option value="">Pilih dokter sesuai jadwal poli</option>';
            list.forEach(function (doctor) {
                const opt = document.createElement('option');
                opt.value = doctor.id;
                opt.textContent = doctor.time ? (doctor.name + ' (' + doctor.time + ')') : doctor.name;
                if (String(doctor.id) === String(current)) opt.selected = true;
                doctorSelect.appendChild(opt);
            });
            if (hint) {
                hint.textContent = fromSchedule
                    ? (roomSelect && roomSelect.value
                        ? 'Dokter sesuai jadwal poli pada tanggal ini.'
                        : 'Dokter yang praktik di cabang ini pada tanggal ini.')
                    : 'Belum ada jadwal poli pada tanggal ini. Menampilkan semua dokter.';
            }
        }

        function loadDoctors() {
            if (!branchSelect || !branchSelect.value || !dateInput || !dateInput.value) {
                fillDoctors(fallbackDoctors, false);
                return;
            }
            const params = new URLSearchParams({
                branch_id: branchSelect.value,
                visit_date: dateInput.value,
            });
            if (roomSelect && roomSelect.value) params.set('room_id', roomSelect.value);
            fetch(availabilityUrl + '?' + params.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function (response) {
                return response.json();
            }).then(function (payload) {
                const doctors = payload.doctors || [];
                fillDoctors(doctors.length ? doctors : fallbackDoctors, doctors.length > 0);
            }).catch(function () {
                fillDoctors(fallbackDoctors, false);
            });
        }

        if (branchSelect) branchSelect.addEventListener('change', function () {
            filterRooms();
            loadDoctors();
            if (! (satusehatId && satusehatId.value) && ((patientId && patientId.value) || (nikInput && String(nikInput.value || '').replace(/\D+/g, '').length === 16))) {
                lookupPatientIhs({ silentMissing: true });
            }
        });
        if (roomSelect) roomSelect.addEventListener('change', loadDoctors);
        if (dateInput) dateInput.addEventListener('change', loadDoctors);

        filterRooms();
        loadDoctors();
    })();
</script>
@endpush
