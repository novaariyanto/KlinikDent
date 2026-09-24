@extends('layouts.public')

@section('title', 'Janji temu — '.$tenant->name)

@section('content')
    <div class="card">
        <div class="card-body p-4">
            <h1 class="h4 mb-1">Janji temu {{ $tenant->name }}</h1>
            <p class="text-muted">Tanpa akun. Pilih sesi dokter yang masih tersedia.</p>

            @if (session('booking'))
                <div class="alert alert-success">
                    Nomor antrean <strong>{{ session('booking.number') }}</strong>
                    untuk {{ session('booking.patient') }}
                    @if (session('booking.doctor')) bersama {{ session('booking.doctor') }} @endif
                    pada {{ session('booking.date') }}.
                    Nomor rekam medis: {{ session('booking.mrn') }}.
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('booking.public.store', $tenant->subdomain) }}" id="booking-form">
                @csrf
                <div style="position:absolute;left:-9999px" aria-hidden="true">
                    <input type="text" name="company_website" tabindex="-1" autocomplete="off">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="branch_id">Cabang</label>
                    <select class="form-select" name="branch_id" id="branch_id" required>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="visit_date">Tanggal</label>
                    <input class="form-control" type="date" name="visit_date" id="visit_date" value="{{ now()->toDateString() }}" min="{{ now()->toDateString() }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="doctor_schedule_id">Sesi dokter</label>
                    <select class="form-select" name="doctor_schedule_id" id="doctor_schedule_id" required>
                        <option value="">Memuat sesi…</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="name">Nama</label>
                    <input class="form-control" name="name" id="name" required value="{{ old('name') }}">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="phone">Telepon</label>
                        <input class="form-control" name="phone" id="phone" required value="{{ old('phone') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="nik">NIK</label>
                        <input class="form-control" name="nik" id="nik" maxlength="16" value="{{ old('nik') }}">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="dob">Tanggal lahir</label>
                    <input class="form-control" type="date" name="dob" id="dob" value="{{ old('dob') }}">
                </div>
                <button class="btn btn-primary" type="submit">Ambil nomor</button>
            </form>
        </div>
    </div>
    <script>
        const sessionsUrl = @json(route('booking.public.sessions', $tenant->subdomain));
        const branch = document.getElementById('branch_id');
        const date = document.getElementById('visit_date');
        const select = document.getElementById('doctor_schedule_id');
        function loadSessions() {
            const params = new URLSearchParams({ branch_id: branch.value, date: date.value });
            fetch(sessionsUrl + '?' + params.toString(), { headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.json(); })
                .then(function (payload) {
                    select.innerHTML = '';
                    if (!payload.sessions.length) {
                        select.innerHTML = '<option value="">Tidak ada sesi tersedia</option>';
                        return;
                    }
                    payload.sessions.forEach(function (session) {
                        const option = document.createElement('option');
                        option.value = session.id;
                        option.textContent = session.doctor + ' ' + session.time + ' (' + session.remaining + ' slot)';
                        select.appendChild(option);
                    });
                });
        }
        branch.addEventListener('change', loadSessions);
        date.addEventListener('change', loadSessions);
        loadSessions();
    </script>
@endsection
