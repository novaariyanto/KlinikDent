@extends('clinical.care.print.layout')

@section('content')
    <div class="doc-title">Rekam Medis Rawat Jalan</div>
    <p class="doc-number">{{ $patient->name }} · RM {{ $patient->medical_record_number }}</p>

    @forelse ($sheets as $sheet)
        @if (! $loop->first)
            <div style="page-break-before: always;"></div>
            <div class="doc-title">Resume Medis Rawat Jalan</div>
        @endif
        @include('clinical.care.print.partials.identity', ['sheet' => $sheet])
        @include('clinical.care.print.partials.soap', ['sheet' => $sheet])
        @foreach ($sheet->visit->medicalRecord?->amendments ?? [] as $amendment)
            <p><strong>Adendum {{ $amendment->created_at?->format('d-M-Y H:i') }}:</strong> {{ $amendment->body }}</p>
        @endforeach
        @include('clinical.care.print.partials.validation', ['sheet' => $sheet, 'visit' => $sheet->visit, 'signatureSrc' => null])
    @empty
        <p>Belum ada kunjungan tercatat.</p>
    @endforelse
@endsection

@section('signature')
@endsection
