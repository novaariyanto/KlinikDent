@extends('layouts.public')

@section('title', 'Cek dokumen')

@section('content')
    <div class="card">
        <div class="card-body p-4">
            <h1 class="h4">Pemeriksaan dokumen</h1>
            <p class="mb-1"><strong>Jenis:</strong> {{ $document->type->label() }}</p>
            <p class="mb-1"><strong>Nomor:</strong> {{ $document->number }}</p>
            <p class="mb-1"><strong>Pasien:</strong> {{ $document->patient_initials ?: '-' }}</p>
            <p class="mb-1"><strong>Tanggal:</strong> {{ $document->signed_at?->format('d M Y') }}</p>
            <p class="mb-0"><strong>Status:</strong> {{ $valid ? 'Berlaku' : 'Dibatalkan' }}</p>
        </div>
    </div>
@endsection
