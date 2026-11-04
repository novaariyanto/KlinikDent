@if (isset($sheet) && $sheet instanceof \App\Support\Clinical\PrintSheet)
    <table class="identity">
        <tr>
            <td class="lbl">No. Registrasi</td>
            <td class="sep">:</td>
            <td class="val">{{ $sheet->registrationNumber() }}</td>
            <td class="lbl">Tgl/Jam Asesmen</td>
            <td class="sep">:</td>
            <td class="val">{{ $sheet->assessedAtLabel() }}</td>
        </tr>
        <tr>
            <td class="lbl">Nama Pasien</td>
            <td class="sep">:</td>
            <td class="val">{{ $sheet->patientName() }}</td>
            <td class="lbl">Klinik/Poli</td>
            <td class="sep">:</td>
            <td class="val">{{ $sheet->poli() }}</td>
        </tr>
        <tr>
            <td class="lbl">No. RM</td>
            <td class="sep">:</td>
            <td class="val">{{ $sheet->medicalRecordNumber() }}</td>
            <td class="lbl">Dokter</td>
            <td class="sep">:</td>
            <td class="val">{{ $sheet->doctorName() }}</td>
        </tr>
        <tr>
            <td class="lbl">Tanggal Lahir / Usia</td>
            <td class="sep">:</td>
            <td class="val">{{ $sheet->birthLabel() }}</td>
            <td class="lbl">Perawat</td>
            <td class="sep">:</td>
            <td class="val">{{ $sheet->nurseName() }}</td>
        </tr>
        <tr>
            <td class="lbl">NIK</td>
            <td class="sep">:</td>
            <td class="val">{{ $sheet->nik() }}</td>
            <td class="lbl">Penjamin</td>
            <td class="sep">:</td>
            <td class="val">{{ $sheet->payerName() }}</td>
        </tr>
        <tr>
            <td class="lbl">J. Kelamin</td>
            <td class="sep">:</td>
            <td class="val">{{ $sheet->genderLabel() }}</td>
            <td class="lbl">No. Asuransi/Kartu</td>
            <td class="sep">:</td>
            <td class="val">{{ $sheet->insuranceNumber() }}</td>
        </tr>
        <tr>
            <td class="lbl">Alamat</td>
            <td class="sep">:</td>
            <td class="val" colspan="4">{{ $sheet->address() }}</td>
        </tr>
    </table>
@endif
