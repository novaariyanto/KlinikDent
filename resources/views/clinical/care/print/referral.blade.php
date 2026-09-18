@extends('clinical.care.print.layout')

@section('content')
    <table class="letter-meta">
        <tr>
            <td>
                <table>
                    <tr><td class="lbl">Nomor</td><td class="sep">:</td><td>{{ $letterNumber }}</td></tr>
                    <tr><td class="lbl">Lampiran</td><td class="sep">:</td><td>-</td></tr>
                    <tr><td class="lbl">Perihal</td><td class="sep">:</td><td>Rujukan Pasien</td></tr>
                </table>
            </td>
            <td style="width: 42%; text-align: right;">
                {{ $printCity ?: $branch?->name }}, {{ $visitDateLabel }}
            </td>
        </tr>
    </table>

    <div class="addressee">
        <p class="to">Kepada Yth.</p>
        <p class="to"><strong>{{ $referral->referred_to }}</strong></p>
        <p class="to">di tempat</p>
    </div>

    <p class="opening">Dengan hormat,</p>
    <p class="opening">Bersama ini kami merujuk pasien tersebut di bawah ini untuk mendapatkan pemeriksaan dan/atau penanganan lebih lanjut sesuai kompetensi Bapak/Ibu.</p>

    <div class="section">
        <table class="section-head"><tr><td>Identitas Pasien</td></tr></table>
        @include('clinical.care.print.partials.identity')
    </div>

    <div class="section">
        <table class="section-head"><tr><td>Diagnosis</td></tr></table>
        @if ($diagnoses->isEmpty())
            <p>Diagnosis belum tercatat pada kunjungan ini.</p>
        @else
            <table class="facts">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Uraian</th>
                        <th style="width: 18%;">Gigi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($diagnoses as $diagnosis)
                        <tr>
                            <td>{{ $diagnosis->code ?: '-' }}</td>
                            <td>{{ $diagnosis->description }}</td>
                            <td>{{ $diagnosis->tooth_number ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="section">
        <table class="section-head"><tr><td>Alasan Rujukan</td></tr></table>
        <table class="facts">
            <tr>
                <th>Alasan</th>
                <td>{{ $referral->reason }}</td>
            </tr>
            @if ($referral->notes)
                <tr>
                    <th>Catatan</th>
                    <td>{{ $referral->notes }}</td>
                </tr>
            @endif
        </table>
    </div>

    <p class="closing">Demikian surat rujukan ini kami sampaikan. Atas perhatian dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.</p>
@endsection
