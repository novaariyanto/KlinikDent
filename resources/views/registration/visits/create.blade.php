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

        if (!input) return;

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
    })();
</script>
@endpush
