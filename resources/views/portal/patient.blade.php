@extends('layouts.public')

@section('title', 'Portal pasien')

@section('content')
    <div class="card">
        <div class="card-body p-4">
            <h1 class="h4">{{ $tenant->name }}</h1>
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @if (! $patient)
                <p class="text-muted">Masuk dengan nomor rekam medis, tanggal lahir, dan 4 digit akhir NIK.</p>
                <form method="POST" action="{{ route('portal.patient.login', $tenant->subdomain) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Nomor rekam medis</label>
                        <input class="form-control" name="medical_record_number" value="{{ old('medical_record_number') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal lahir</label>
                        <input class="form-control" type="date" name="dob" value="{{ old('dob') }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">4 digit akhir NIK</label>
                        <input class="form-control" name="nik_last4" maxlength="4" value="{{ old('nik_last4') }}" required>
                    </div>
                    <button class="btn btn-primary" type="submit">Masuk</button>
                </form>
            @else
                <p>Halo, {{ $patient->name }}. Nomor RM {{ $patient->medical_record_number }}.</p>
                <p><a class="btn btn-primary" href="{{ route('portal.patient.resume', $tenant->subdomain) }}">Unduh rekam medis</a></p>
                <ul>
                    @foreach ($visits as $visit)
                        <li>{{ $visit->visit_date?->format('d M Y') }} — {{ $visit->doctor?->name ?: 'Dokter' }} — {{ $visit->medicalRecord?->chief_complaint ?: 'Tanpa keluhan tercatat' }}</li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ route('portal.patient.logout', $tenant->subdomain) }}">
                    @csrf
                    <button class="btn btn-link" type="submit">Keluar</button>
                </form>
            @endif
        </div>
    </div>
@endsection
