@extends('clinical.care.print.layout')

@section('content')
    <div class="doc-title">Resume Medis</div>
    <p class="doc-number">Nomor: {{ $letterNumber }}</p>
    @include('clinical.care.print.partials.identity')
    <div class="section">
        <table class="facts">
            <tr><th>Keluhan</th><td>{{ $visit->medicalRecord?->chief_complaint ?: '-' }}</td></tr>
            <tr><th>Anamnesis</th><td>{{ $visit->medicalRecord?->anamnesis ?: '-' }}</td></tr>
            <tr><th>Pemeriksaan</th><td>{{ $visit->medicalRecord?->initial_examination ?: '-' }}</td></tr>
            <tr><th>Catatan</th><td>{{ $visit->medicalRecord?->clinical_notes ?: '-' }}</td></tr>
            <tr><th>Diagnosis</th><td>{{ $visit->diagnoses->map(fn ($diagnosis) => $diagnosis->code.' '.$diagnosis->description)->implode('; ') ?: '-' }}</td></tr>
            <tr><th>Tindakan</th><td>{{ $visit->procedureRecords->map(fn ($row) => ($row->icd9_code ? $row->icd9_code.' ' : '').($row->procedure?->name))->implode('; ') ?: '-' }}</td></tr>
        </table>
    </div>
    @foreach ($visit->medicalRecord?->amendments ?? [] as $amendment)
        <p><strong>Adendum {{ $amendment->created_at?->format('d M Y H:i') }}:</strong> {{ $amendment->body }}</p>
    @endforeach
@endsection
