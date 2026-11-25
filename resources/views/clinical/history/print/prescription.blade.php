@extends('clinical.care.print.layout')

@section('content')
    <div class="doc-title">Resep Obat</div>
    <p class="doc-number">Nomor: {{ $letterNumber }} · Status: {{ $prescription->status?->label() ?? '-' }}</p>
    @include('clinical.care.print.partials.identity')

    @include('clinical.care.print.partials.recipe-items', ['items' => $prescription->items])
    <p style="margin-top:6px">Penulis resep: {{ $prescription->doctor?->name ?: ($visit->doctor?->name ?: '-') }} · Dibuat {{ $prescription->created_at?->format('d-M-Y H:i') }}</p>
@endsection
