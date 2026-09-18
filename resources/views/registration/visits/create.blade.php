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
        const selected = document.getElementById('selected-patient');
        const newFields = document.getElementById('new-patient-fields');
        const searchUrl = @json(route('registration.patients.search'));
        let timer = null;

        if (input) {
            input.addEventListener('input', function () {
                clearTimeout(timer);
                const q = input.value.trim();
                if (q.length < 2) {
                    results.classList.add('d-none');
                    results.innerHTML = '';
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
                            a.textContent = row.medical_record_number + ' — ' + row.name;
                            a.addEventListener('click', function (e) {
                                e.preventDefault();
                                patientId.value = row.id;
                                selected.textContent = 'Terpilih: ' + row.medical_record_number + ' — ' + row.name;
                                newFields.classList.add('d-none');
                                if (row.default_payer_id) {
                                    const payer = document.getElementById('payer_id');
                                    if (payer) payer.value = row.default_payer_id;
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
        });
        if (roomSelect) roomSelect.addEventListener('change', loadDoctors);
        if (dateInput) dateInput.addEventListener('change', loadDoctors);

        filterRooms();
        loadDoctors();
    })();
</script>
@endpush
