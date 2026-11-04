@extends('clinical.care.print.layout')

@section('content')
    <div class="doc-title">Instruksi Perawatan Pasien</div>
    <p class="doc-number">Nomor: {{ $letterNumber }}</p>
    @include('clinical.care.print.partials.identity')

    <div class="section">
        <table class="section-head"><tr><td>Instruksi yang Harus Dijalankan</td></tr></table>
        @if ($items->isEmpty() && $extra === '')
            <p>{{ $clinicalNotes ?: 'Belum ada instruksi tercatat pada kunjungan ini.' }}</p>
        @else
            @if ($items->isNotEmpty())
                <ol class="instructions">
                    @foreach ($items as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ol>
            @endif
            @if ($extra !== '')
                <p style="margin-top: 8px;">{{ $extra }}</p>
            @endif
        @endif
    </div>

    <p class="closing">Mohon instruksi di atas dilaksanakan sesuai anjuran dokter. Apabila timbul keluhan yang memburuk, segera kembali ke klinik atau fasilitas kesehatan terdekat.</p>
@endsection
