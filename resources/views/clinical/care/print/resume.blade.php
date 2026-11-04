@extends('clinical.care.print.layout')

@section('content')
    <div class="doc-title">Resume Medis Rawat Jalan</div>
    @if (! empty($letterNumber))
        <p class="doc-number">Nomor: {{ $letterNumber }}</p>
    @endif
    @include('clinical.care.print.partials.identity')
    @include('clinical.care.print.partials.soap')
    @foreach ($visit->medicalRecord?->amendments ?? [] as $amendment)
        <p><strong>Adendum {{ $amendment->created_at?->format('d-M-Y H:i') }}:</strong> {{ $amendment->body }}</p>
    @endforeach
@endsection
