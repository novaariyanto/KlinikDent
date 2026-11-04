@if (isset($sheet) && $sheet instanceof \App\Support\Clinical\PrintSheet)
    <table class="soap">
        <tr>
            <th class="group" rowspan="5">Subyektif</th>
            <th class="label">Anamnesa</th>
            <td>{!! nl2br(e($sheet->anamnesis())) !!}</td>
        </tr>
        <tr>
            <th class="label">Alergi Makanan</th>
            <td>{{ $sheet->allergy('food') }}</td>
        </tr>
        <tr>
            <th class="label">Alergi Udara</th>
            <td>{{ $sheet->allergy('air') }}</td>
        </tr>
        <tr>
            <th class="label">Alergi Obat</th>
            <td>{{ $sheet->allergy('drug') }}</td>
        </tr>
        <tr>
            <th class="label">Alergi Lainnya</th>
            <td>{{ $sheet->allergy('other') }}</td>
        </tr>
        <tr>
            <th class="group" rowspan="3">Obyektif</th>
            <th class="label">Vital Sign</th>
            <td>
                <table class="vitals">
                    <tr>
                        <th>Sistole</th>
                        <th>Diastole</th>
                        <th>Nadi</th>
                        <th>RR</th>
                        <th>Suhu</th>
                        <th>Berat Badan</th>
                        <th>Tinggi Badan</th>
                        <th>Lingkar Perut</th>
                    </tr>
                    <tr>
                        <td>{{ $sheet->vital('sys') }}</td>
                        <td>{{ $sheet->vital('dia') }}</td>
                        <td>{{ $sheet->vital('pulse') }}</td>
                        <td>{{ $sheet->vital('respiration') }}</td>
                        <td>{{ $sheet->vital('temperature') }}</td>
                        <td>{{ $sheet->vital('weight') }}</td>
                        <td>{{ $sheet->vital('height') }}</td>
                        <td>{{ $sheet->vital('waist') }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <th class="label">Pemeriksaan Fisik</th>
            <td>{{ $sheet->physicalExam() }}</td>
        </tr>
        <tr>
            <th class="label">Penunjang</th>
            <td>{{ $sheet->supporting() }}</td>
        </tr>
        <tr>
            <th class="group" rowspan="3">Assesmen</th>
            <th class="label">Diagnosa ICD-10</th>
            <td>{!! nl2br(e($sheet->icd10())) !!}</td>
        </tr>
        <tr>
            <th class="label">Diagnosa ICD-9-CM</th>
            <td>{!! nl2br(e($sheet->icd9())) !!}</td>
        </tr>
        <tr>
            <th class="label">Diagnosa Keperawatan</th>
            <td>{{ $sheet->nursingDiagnosis() }}</td>
        </tr>
        <tr>
            <th class="group" rowspan="4">Planning</th>
            <th class="label">Rencana Asuhan</th>
            <td>{{ $sheet->carePlan() }}</td>
        </tr>
        <tr>
            <th class="label">Catatan Khusus</th>
            <td>{!! nl2br(e($sheet->specialNotes())) !!}</td>
        </tr>
        <tr>
            <th class="label">Terapi/Resep</th>
            <td>{!! nl2br(e($sheet->therapy())) !!}</td>
        </tr>
        <tr>
            <th class="label">Tindakan</th>
            <td>{!! nl2br(e($sheet->procedures())) !!}</td>
        </tr>
    </table>

    <table class="soap">
        <tr>
            <th class="group">Skrining</th>
            <td>
                Keluhan: {{ $sheet->screeningComplaint() }}
                &nbsp;&nbsp; Alergi Obat: {{ $sheet->screeningDrugAllergy() }}
                &nbsp;&nbsp; Prolanis: {{ $sheet->screeningProlanis() }}
            </td>
        </tr>
    </table>
@endif
