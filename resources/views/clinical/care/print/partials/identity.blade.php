<table class="facts">
    <tr>
        <th>Nama pasien</th>
        <td>{{ $patient?->name ?: '-' }}</td>
    </tr>
    <tr>
        <th>Nomor rekam medis</th>
        <td>{{ $patient?->medical_record_number ?: '-' }}</td>
    </tr>
    <tr>
        <th>Jenis kelamin / umur</th>
        <td>{{ $patient?->gender?->label() ?: '-' }} / {{ $patient?->ageLabel() }}</td>
    </tr>
    <tr>
        <th>Tanggal kunjungan</th>
        <td>{{ $visitDateLabel }}</td>
    </tr>
    <tr>
        <th>Dokter pemeriksa</th>
        <td>{{ $visit->doctor?->name ?: '-' }}</td>
    </tr>
    @if ($patient?->address)
        <tr>
            <th>Alamat</th>
            <td>{{ $patient->address }}</td>
        </tr>
    @endif
</table>
