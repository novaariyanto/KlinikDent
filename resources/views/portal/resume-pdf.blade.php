<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekam medis {{ $patient->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1 { font-size: 16px; }
        h2 { font-size: 13px; margin-top: 16px; }
    </style>
</head>
<body>
    <h1>{{ $tenant->name }}</h1>
    <p>{{ $patient->name }} · RM {{ $patient->medical_record_number }}</p>
    @foreach ($visits as $visit)
        <h2>{{ $visit->visit_date?->format('d M Y') }} — {{ $visit->doctor?->name }}</h2>
        <p><strong>Keluhan:</strong> {{ $visit->medicalRecord?->chief_complaint ?: '-' }}</p>
        <p><strong>Anamnesis:</strong> {{ $visit->medicalRecord?->anamnesis ?: '-' }}</p>
        <p><strong>Catatan:</strong> {{ $visit->medicalRecord?->clinical_notes ?: '-' }}</p>
        <p><strong>Diagnosis:</strong>
            {{ $visit->diagnoses->map(fn ($diagnosis) => $diagnosis->code.' '.$diagnosis->description)->implode('; ') ?: '-' }}
        </p>
        @if ($visit->medicalRecord?->amendments)
            @foreach ($visit->medicalRecord->amendments as $amendment)
                <p><strong>Adendum {{ $amendment->created_at?->format('d M Y H:i') }}:</strong> {{ $amendment->body }}</p>
            @endforeach
        @endif
    @endforeach
</body>
</html>
