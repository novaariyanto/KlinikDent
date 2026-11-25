@extends('clinical.care.print.layout')

@section('content')
    <div class="doc-title">Resep Obat</div>
    <p class="doc-number">Nomor: {{ $letterNumber }} · {{ $prescriptions->count() }} resep</p>
    @include('clinical.care.print.partials.identity')

    @foreach ($prescriptions as $prescription)
        <table class="section-head" style="margin-top:{{ $loop->first ? '0' : '10px' }}">
            <tr>
                <td>Resep #{{ $prescription->id }} · {{ $prescription->status?->label() ?? '-' }} · {{ $prescription->doctor?->name ?: ($visit->doctor?->name ?: '-') }}</td>
            </tr>
        </table>
        @include('clinical.care.print.partials.recipe-items', ['items' => $prescription->items])
    @endforeach
@endsection
