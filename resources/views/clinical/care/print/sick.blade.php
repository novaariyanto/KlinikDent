@extends('clinical.care.print.layout')

@section('content')
    <div class="doc-title">Surat Keterangan Sakit</div>
    <p class="doc-number">Nomor: {{ $letterNumber }}</p>
    <p class="opening">Yang bertanda tangan di bawah ini menerangkan bahwa:</p>
    @include('clinical.care.print.partials.identity')
    <p class="opening">perlu beristirahat selama {{ $document->payload['rest_days'] ?? '-' }} hari, terhitung mulai {{ $document->payload['rest_from'] ?? '-' }}.</p>
    @if (! empty($document->payload['notes']))
        <p>{{ $document->payload['notes'] }}</p>
    @endif
@endsection
