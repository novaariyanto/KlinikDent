@extends('clinical.care.print.layout')

@section('content')
    <table style="width: 100%; margin-bottom: 12px;">
        <tr>
            <td style="text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase; text-decoration: underline; padding-bottom: 4px;">
                Instruksi Perawatan Pasien
            </td>
        </tr>
        <tr>
            <td style="text-align: center; font-size: 10.5px; padding-bottom: 8px;">
                Nomor: {{ $letterNumber }}
            </td>
        </tr>
    </table>

    <div class="section">
        <table class="section-head"><tr><td>Identitas Pasien</td></tr></table>
        @include('clinical.care.print.partials.identity')
    </div>

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
