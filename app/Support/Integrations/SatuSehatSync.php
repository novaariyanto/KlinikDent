<?php

namespace App\Support\Integrations;

use App\Enums\RoomType;
use App\Enums\SatuSehatStatus;
use App\Enums\VisitStatus;
use App\Models\Room;
use App\Support\Clinical\CareExamOptions;
use App\Models\BranchSatuSehatSetting;
use App\Models\Diagnosis;
use App\Models\Doctor;
use App\Models\OdontogramLog;
use App\Models\OdontogramTooth;
use App\Models\Patient;
use App\Models\ProcedureRecord;
use App\Models\SatuSehatResource;
use App\Models\TenantIntegration;
use App\Models\Visit;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SatuSehatSync
{
    /** @var list<string> */
    protected array $notes = [];

    /**
     * Panggilan FHIR pada pencarian IHS terakhir.
     *
     * @var list<array{method: string, url: string, request: mixed, status: int|null, response: mixed}>
     */
    public array $exchanges = [];

    public function __construct(
        protected TenantIntegration $tenant,
        protected BranchSatuSehatSetting $branch,
    ) {}

    /**
     * @return array{ok: bool, id?: string, response: array<string, mixed>, error?: string}
     */
    public function run(Visit $visit): array
    {
        $this->notes = [];
        $visit->loadMissing([
            'patient',
            'doctor.doctorProfile',
            'diagnoses',
            'procedureRecords.procedure',
            'medicalRecord',
            'odontogramLogs',
            'referrals',
            'room',
            'branch',
        ]);

        $blocked = $this->blockedReason($visit);

        if ($blocked !== null) {
            return [
                'ok' => false,
                'response' => ['skipped' => $this->notes],
                'error' => $blocked,
            ];
        }

        $client = new SatuSehatClient($this->tenant, $this->branch);

        try {
            $patientIhs = $this->patientIhs($client, $visit->patient);
            $practitionerIhs = $this->practitionerIhs($client, $visit->doctor->doctorProfile);
            $start = $this->periodStart($visit);
            $end = $this->periodEnd($visit, $start);

            $location = $this->encounterLocation($client, $visit);
            $encounterId = $this->send(
                $client,
                'Encounter',
                'encounter:'.$visit->id,
                $this->encounterPayload($visit, $patientIhs, $practitionerIhs, $location, $start, $end, false, []),
                $visit,
            );

            $conditionIds = [];

            foreach ($visit->diagnoses as $index => $diagnosis) {
                $conditionIds[] = $this->send(
                    $client,
                    'Condition',
                    'condition:'.$diagnosis->id,
                    $this->conditionPayload($diagnosis, $patientIhs, $encounterId, $start),
                    $diagnosis,
                );
                unset($index);
            }

            foreach ($visit->procedureRecords as $record) {
                if (! filled($record->icd9_code)) {
                    $this->notes[] = 'Tindakan #'.$record->id.' tidak dikirim karena kode ICD-9-CM kosong.';

                    continue;
                }

                $this->send(
                    $client,
                    'Procedure',
                    'procedure:'.$record->id,
                    $this->procedurePayload($record, $patientIhs, $practitionerIhs, $encounterId, $start, $end),
                    $record,
                );
            }

            $this->sendObservations($client, $visit, $patientIhs, $practitionerIhs, $encounterId, $start);

            $encounterId = $this->send(
                $client,
                'Encounter',
                'encounter:'.$visit->id,
                $this->encounterPayload($visit, $patientIhs, $practitionerIhs, $location, $start, $end, true, $conditionIds),
                $visit,
            );

            return [
                'ok' => true,
                'id' => $encounterId,
                'response' => [
                    'encounter_id' => $encounterId,
                    'skipped' => $this->notes,
                    'exchanges' => $client->exchanges,
                ],
            ];
        } catch (IntegrationException $exception) {
            return [
                'ok' => false,
                'response' => [
                    'skipped' => $this->notes,
                    'exchanges' => $client->exchanges,
                ],
                'error' => $exception->getMessage(),
            ];
        }
    }

    protected function blockedReason(Visit $visit): ?string
    {
        if ($visit->status !== VisitStatus::Done) {
            return 'Hanya kunjungan selesai yang dikirim ke SATUSEHAT.';
        }

        if (! $this->tenant->enabled) {
            return 'Modul SATUSEHAT belum diaktifkan untuk klinik ini.';
        }

        if (! $this->branch->readyToSync()) {
            return 'Sinkronisasi cabang ini belum aktif. Uji koneksi dan pilih lokasi pelayanan terlebih dahulu.';
        }

        if (! filled($visit->patient?->nik)) {
            return 'Pasien belum memiliki NIK, sehingga nomor IHS tidak dapat dicari.';
        }

        $doctor = $visit->doctor?->doctorProfile;

        if (! $doctor || ! filled($doctor->nik)) {
            return 'Dokter pada kunjungan ini belum memiliki NIK di profil tenaga medis.';
        }

        return null;
    }

    public function ensurePatientIhs(Patient $patient): string
    {
        $client = new SatuSehatClient($this->tenant, $this->branch);

        try {
            return $this->patientIhs($client, $patient);
        } finally {
            $this->exchanges = $client->exchanges;
        }
    }

    protected function patientIhs(SatuSehatClient $client, Patient $patient): string
    {
        if (filled($patient->satusehat_id)) {
            return (string) $patient->satusehat_id;
        }

        $id = $client->findByNik('Patient', (string) $patient->nik);

        if ($id === null) {
            $id = $this->registerMissingPatient($client, $patient);
        }

        $patient->forceFill(['satusehat_id' => $id])->save();

        return $id;
    }

    protected function registerMissingPatient(SatuSehatClient $client, Patient $patient): string
    {
        $missing = [];

        if (! filled($patient->name)) {
            $missing[] = 'nama';
        }

        if ($patient->dob === null) {
            $missing[] = 'tanggal lahir';
        }

        if ($patient->gender === null) {
            $missing[] = 'jenis kelamin';
        }

        if ($missing !== []) {
            throw new IntegrationException(
                'NIK pasien tidak ditemukan di MPI SATUSEHAT. Pendaftaran IHS membutuhkan '.implode(', ', $missing).' sesuai KTP.'
            );
        }

        $payload = [
            'resourceType' => 'Patient',
            'meta' => [
                'profile' => ['https://fhir.kemkes.go.id/r4/StructureDefinition/Patient'],
            ],
            'identifier' => [[
                'use' => 'official',
                'system' => SatuSehatCodes::NIK_SYSTEM,
                'value' => (string) $patient->nik,
            ]],
            'active' => true,
            'name' => [[
                'use' => 'official',
                'text' => $patient->name,
            ]],
            'gender' => $patient->gender->value,
            'birthDate' => $patient->dob->format('Y-m-d'),
            'deceasedBoolean' => false,
            'multipleBirthInteger' => 0,
        ];

        if (filled($patient->phone)) {
            $payload['telecom'] = [[
                'system' => 'phone',
                'value' => (string) $patient->phone,
                'use' => 'mobile',
            ]];
        }

        if (filled($patient->address)) {
            $payload['address'] = [[
                'use' => 'home',
                'line' => [(string) $patient->address],
                'country' => 'ID',
            ]];
        }

        return $this->send($client, 'Patient', 'patient:'.$patient->id, $payload, $patient);
    }

    protected function practitionerIhs(SatuSehatClient $client, Doctor $doctor): string
    {
        if (filled($doctor->satusehat_id)) {
            return (string) $doctor->satusehat_id;
        }

        $id = $client->findByNik('Practitioner', (string) $doctor->nik);

        if ($id === null) {
            throw new IntegrationException('NIK dokter tidak ditemukan sebagai Practitioner di SATUSEHAT.');
        }

        $doctor->forceFill(['satusehat_id' => $id])->save();

        return $id;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function send(
        SatuSehatClient $client,
        string $type,
        string $localKey,
        array $payload,
        ?Model $internal = null,
    ): string {
        $existing = SatuSehatResource::withoutGlobalScopes()
            ->where('tenant_id', $this->branch->tenant_id)
            ->where('local_key', $localKey)
            ->first();

        if ($existing?->satusehat_id && $existing->sync_status === SatuSehatStatus::Synced && ! in_array($type, ['Encounter', 'Location'], true)) {
            return (string) $existing->satusehat_id;
        }

        $result = $client->submit($type, $payload, $existing?->satusehat_id, $localKey);
        $this->remember($type, $localKey, $internal, $result['id'] ?? $existing?->satusehat_id, $result['ok'], $result['error'] ?? null);

        if (! $result['ok']) {
            throw new IntegrationException($result['error'] ?? 'Pengiriman '.$type.' gagal.');
        }

        return (string) $result['id'];
    }

    protected function remember(
        string $type,
        string $localKey,
        ?Model $internal,
        ?string $remoteId,
        bool $ok,
        ?string $error,
    ): void {
        SatuSehatResource::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $this->branch->tenant_id,
                'local_key' => $localKey,
            ],
            [
                'branch_id' => $this->branch->branch_id,
                'resource_type' => $type,
                'internal_type' => $internal ? $internal::class : null,
                'internal_id' => $internal?->getKey(),
                'satusehat_id' => $remoteId,
                'sync_status' => $ok ? SatuSehatStatus::Synced : SatuSehatStatus::Failed,
                'last_synced_at' => $ok ? now() : null,
                'last_error' => $ok ? null : $error,
            ],
        );
    }

    protected function sendObservations(
        SatuSehatClient $client,
        Visit $visit,
        string $patientIhs,
        string $practitionerIhs,
        string $encounterId,
        CarbonInterface $at,
    ): void {
        $record = $visit->medicalRecord;
        $vitals = $record?->vital_signs ?? [];
        $systolic = $vitals['blood_pressure_sys'] ?? null;
        $diastolic = $vitals['blood_pressure_dia'] ?? null;

        if (($systolic === null || $diastolic === null) && filled($vitals['blood_pressure'] ?? null) && str_contains((string) $vitals['blood_pressure'], '/')) {
            [$systolic, $diastolic] = array_pad(explode('/', (string) $vitals['blood_pressure'], 2), 2, null);
        }

        $this->quantityObservation($client, $visit, $patientIhs, $practitionerIhs, $encounterId, $at, '8480-6', 'Systolic blood pressure', $systolic, 'mmHg', 'mm[Hg]');
        $this->quantityObservation($client, $visit, $patientIhs, $practitionerIhs, $encounterId, $at, '8462-4', 'Diastolic blood pressure', $diastolic, 'mmHg', 'mm[Hg]');
        $this->quantityObservation($client, $visit, $patientIhs, $practitionerIhs, $encounterId, $at, '8867-4', 'Heart rate', $vitals['pulse'] ?? null, '/min', '/min');
        $this->quantityObservation($client, $visit, $patientIhs, $practitionerIhs, $encounterId, $at, '8310-5', 'Body temperature', $vitals['temperature'] ?? null, 'Cel', 'Cel');
        $this->quantityObservation($client, $visit, $patientIhs, $practitionerIhs, $encounterId, $at, '9279-1', 'Respiratory rate', $vitals['respiration'] ?? null, '/min', '/min');

        $exam = $record?->dental_exam ?? [];
        $this->codedExam(
            $client,
            $visit,
            $patientIhs,
            $practitionerIhs,
            $encounterId,
            $at,
            'occlusion',
            SatuSehatCodes::coding(SatuSehatCodes::SNOMED, '25272006', 'Dental occlusion'),
            $exam['occlusion'] ?? null,
            SatuSehatCodes::OCCLUSION,
            SatuSehatCodes::SNOMED,
            'Oklusi',
        );

        $this->codedExam(
            $client,
            $visit,
            $patientIhs,
            $practitionerIhs,
            $encounterId,
            $at,
            'torus-mandibularis',
            SatuSehatCodes::coding(SatuSehatCodes::SNOMED, '11625007', 'Torus mandibularis'),
            $exam['torus_mandibularis'] ?? null,
            SatuSehatCodes::TORUS_MANDIBULARIS,
            SatuSehatCodes::CLINICAL,
            'Torus mandibularis',
        );

        $this->codedExam(
            $client,
            $visit,
            $patientIhs,
            $practitionerIhs,
            $encounterId,
            $at,
            'torus-palatinus',
            SatuSehatCodes::coding(SatuSehatCodes::SNOMED, '46752004', 'Torus palatinus'),
            $exam['torus_palatinus'] ?? null,
            SatuSehatCodes::TORUS_PALATINUS,
            SatuSehatCodes::CLINICAL,
            'Torus palatinus',
        );
        $this->codedExam(
            $client,
            $visit,
            $patientIhs,
            $practitionerIhs,
            $encounterId,
            $at,
            'palate',
            SatuSehatCodes::coding(SatuSehatCodes::LOINC, '32460-8', 'Physical findings of Palate'),
            $exam['palate'] ?? null,
            SatuSehatCodes::PALATE,
            SatuSehatCodes::CLINICAL,
            'Palatum',
        );
        $this->codedExam(
            $client,
            $visit,
            $patientIhs,
            $practitionerIhs,
            $encounterId,
            $at,
            'diastema',
            SatuSehatCodes::coding(SatuSehatCodes::SNOMED, '734009000', 'Diastema of teeth'),
            $exam['diastema'] ?? null,
            SatuSehatCodes::PRESENCE,
            SatuSehatCodes::CLINICAL,
            'Diastema',
            filled($exam['diastema_elements'] ?? null) ? (string) $exam['diastema_elements'] : null,
        );
        $this->codedExam(
            $client,
            $visit,
            $patientIhs,
            $practitionerIhs,
            $encounterId,
            $at,
            'anomaly',
            SatuSehatCodes::coding(SatuSehatCodes::SNOMED, '81256000', 'Anomaly of tooth position'),
            $exam['anomaly'] ?? null,
            SatuSehatCodes::ANOMALY,
            SatuSehatCodes::CLINICAL,
            'Gigi anomali',
            filled($exam['anomaly_description'] ?? null) ? (string) $exam['anomaly_description'] : null,
        );

        $this->send(
            $client,
            'Observation',
            'observation:other-oral:'.$visit->id,
            $this->observationBase(
                $patientIhs,
                $practitionerIhs,
                $encounterId,
                $at,
                'exam',
                'Exam',
                SatuSehatCodes::coding(SatuSehatCodes::CLINICAL, 'OC000060', 'Kondisi Gigi dan Mulut Lainnya'),
            ) + ['valueString' => $this->otherOralCondition($exam)],
            $visit->medicalRecord,
        );

        $this->sendOdontogram($client, $visit, $patientIhs, $practitionerIhs, $encounterId, $at);
        $this->sendToothCounts($client, $visit, $patientIhs, $practitionerIhs, $encounterId, $at);
    }

    protected function quantityObservation(
        SatuSehatClient $client,
        Visit $visit,
        string $patientIhs,
        string $practitionerIhs,
        string $encounterId,
        CarbonInterface $at,
        string $code,
        string $display,
        mixed $value,
        string $unit,
        string $unitCode,
    ): void {
        if (! is_numeric($value)) {
            return;
        }

        $this->send(
            $client,
            'Observation',
            'observation:vital:'.$visit->id.':'.$code,
            $this->observationBase($patientIhs, $practitionerIhs, $encounterId, $at, 'vital-signs', 'Vital Signs', SatuSehatCodes::coding(SatuSehatCodes::LOINC, $code, $display)) + [
                'valueQuantity' => [
                    'value' => (float) $value,
                    'unit' => $unit,
                    'system' => 'http://unitsofmeasure.org',
                    'code' => $unitCode,
                ],
            ],
            $visit->medicalRecord,
        );
    }

    /**
     * @param  array{system: string, code: string, display: string}  $observationCode
     * @param  array<string, array{0: string, 1: string}>  $map
     */
    protected function codedExam(
        SatuSehatClient $client,
        Visit $visit,
        string $patientIhs,
        string $practitionerIhs,
        string $encounterId,
        CarbonInterface $at,
        string $key,
        array $observationCode,
        mixed $value,
        array $map,
        string $valueSystem,
        string $label,
        ?string $text = null,
    ): void {
        if (! filled($value)) {
            return;
        }

        $mapped = $map[(string) $value] ?? null;

        if ($mapped === null) {
            $this->notes[] = $label.' "'.$value.'" tidak dikirim karena belum punya kode resmi SATUSEHAT.';

            return;
        }

        $concept = [
            'coding' => [SatuSehatCodes::coding($valueSystem, $mapped[0], $mapped[1])],
        ];

        if ($text) {
            $concept['text'] = $text;
        }

        $this->send(
            $client,
            'Observation',
            'observation:'.$key.':'.$visit->id,
            $this->observationBase($patientIhs, $practitionerIhs, $encounterId, $at, 'exam', 'Exam', $observationCode) + [
                'valueCodeableConcept' => $concept,
            ],
            $visit->medicalRecord,
        );
    }

    /**
     * Playbook OC000060 memakai teks bebas. Default resmi bila tidak ada temuan: "Tidak ada".
     *
     * @param  array<string, mixed>  $exam
     */
    protected function otherOralCondition(array $exam): string
    {
        $parts = [];

        if (($exam['extraoral'] ?? null) === 'abnormal') {
            $parts[] = 'Ekstraoral: '.(filled($exam['extraoral_note'] ?? null) ? $exam['extraoral_note'] : 'Ada kelainan');
        }

        if (($exam['intraoral'] ?? null) === 'abnormal') {
            $parts[] = 'Intraoral: '.(filled($exam['intraoral_note'] ?? null) ? $exam['intraoral_note'] : 'Ada kelainan');
        }

        $gingiva = (string) ($exam['gingiva'] ?? '');

        if ($gingiva !== '' && $gingiva !== 'normal') {
            $parts[] = 'Gingiva: '.(CareExamOptions::gingiva()[$gingiva] ?? $gingiva);
        }

        $mucosa = (string) ($exam['mucosa'] ?? '');

        if ($mucosa !== '' && $mucosa !== 'normal') {
            $parts[] = 'Mukosa: '.(CareExamOptions::mucosa()[$mucosa] ?? $mucosa);
        }

        if (($exam['supernumerary'] ?? null) === 'present') {
            $parts[] = 'Gigi supernumerary: '.(filled($exam['supernumerary_teeth'] ?? null) ? $exam['supernumerary_teeth'] : 'Ada');
        }

        if (filled($exam['habits'] ?? null)) {
            $parts[] = (string) $exam['habits'];
        }

        return $parts === [] ? 'Tidak ada' : implode('. ', $parts);
    }

    protected function sendOdontogram(
        SatuSehatClient $client,
        Visit $visit,
        string $patientIhs,
        string $practitionerIhs,
        string $encounterId,
        CarbonInterface $at,
    ): void {
        $teeth = OdontogramTooth::query()
            ->where('patient_id', $visit->patient_id)
            ->get()
            ->keyBy('tooth_number');

        $logs = $visit->odontogramLogs
            ->sortBy('id')
            ->groupBy('tooth_number')
            ->map(fn ($group) => $group->last());

        foreach ($logs as $log) {
            if (! $log instanceof OdontogramLog) {
                continue;
            }

            $toothCode = SatuSehatCodes::tooth((string) $log->tooth_number);

            if ($toothCode === null) {
                $this->notes[] = 'Gigi '.$log->tooth_number.' tidak dikirim karena bukan nomenklatur FDI yang dikenal.';

                continue;
            }

            $status = $log->new_status?->value;
            $mapped = SatuSehatCodes::TOOTH_STATUS[$status] ?? null;

            if ($mapped === null) {
                $this->notes[] = 'Gigi '.$log->tooth_number.' status '.($log->new_status?->label() ?: $status).' tidak dikirim karena belum punya kode resmi.';

                continue;
            }

            $components = [[
                'code' => SatuSehatCodes::codeable($this->componentCode($mapped['component'])),
                'valueCodeableConcept' => SatuSehatCodes::codeable(SatuSehatCodes::coding($mapped['system'], $mapped['code'], $mapped['display'])),
            ]];

            $current = $teeth->get($log->tooth_number);

            if ($current && $current->status?->value === $status) {
                foreach (array_keys($current->surfaces ?? []) as $surface) {
                    $surfaceCode = SatuSehatCodes::SURFACES[(string) $surface] ?? null;

                    if ($surfaceCode === null) {
                        $this->notes[] = 'Permukaan '.$surface.' pada gigi '.$log->tooth_number.' tidak dikirim.';

                        continue;
                    }

                    $components[] = [
                        'code' => SatuSehatCodes::codeable(SatuSehatCodes::coding(SatuSehatCodes::LOINC, '32889-8', 'Surface [Identifier] Tooth')),
                        'valueCodeableConcept' => SatuSehatCodes::codeable(SatuSehatCodes::coding(SatuSehatCodes::SNOMED, $surfaceCode[0], $surfaceCode[1])),
                    ];
                }
            }

            $this->send(
                $client,
                'Observation',
                'observation:tooth:'.$log->id,
                $this->observationBase(
                    $patientIhs,
                    $practitionerIhs,
                    $encounterId,
                    $at,
                    'exam',
                    'Exam',
                    SatuSehatCodes::coding(SatuSehatCodes::CLINICAL, 'OC000061', 'Pemeriksaan Odontogram'),
                ) + [
                    'valueBoolean' => true,
                    'bodySite' => SatuSehatCodes::codeable(SatuSehatCodes::coding(SatuSehatCodes::SNOMED, $toothCode[0], $toothCode[1])),
                    'component' => $components,
                ],
                $log,
            );
        }
    }

    protected function sendToothCounts(
        SatuSehatClient $client,
        Visit $visit,
        string $patientIhs,
        string $practitionerIhs,
        string $encounterId,
        CarbonInterface $at,
    ): void {
        $teeth = OdontogramTooth::query()->where('patient_id', $visit->patient_id)->get();

        if ($teeth->isEmpty()) {
            return;
        }

        $value = fn (string ...$statuses) => $teeth->filter(
            fn (OdontogramTooth $tooth) => in_array($tooth->status?->value, $statuses, true)
        )->count();

        $counts = [
            'decayed' => [$value('caries'), '251319000', 'Decayed tooth count'],
            'missing' => [$value('missing', 'extracted'), '251317003', 'Missing tooth count'],
            'filled' => [$value('filling'), '251318008', 'Filled tooth count'],
        ];

        foreach ($counts as $key => [$count, $code, $display]) {
            $this->send(
                $client,
                'Observation',
                'observation:'.$key.':'.$visit->id,
                $this->observationBase(
                    $patientIhs,
                    $practitionerIhs,
                    $encounterId,
                    $at,
                    'exam',
                    'Exam',
                    SatuSehatCodes::coding(SatuSehatCodes::SNOMED, $code, $display),
                ) + ['valueInteger' => $count],
                $visit->medicalRecord,
            );
        }
    }

    /**
     * @return array{system: string, code: string, display: string}
     */
    protected function componentCode(string $kind): array
    {
        if ($kind === 'restoration') {
            return SatuSehatCodes::coding(SatuSehatCodes::SNOMED, '251335007', 'Dental restoration or prosthesis shade');
        }

        return SatuSehatCodes::coding(SatuSehatCodes::SNOMED, '278544002', 'Tooth finding');
    }

    /**
     * @param  array{system: string, code: string, display: string}  $code
     * @return array<string, mixed>
     */
    protected function observationBase(
        string $patientIhs,
        string $practitionerIhs,
        string $encounterId,
        CarbonInterface $at,
        string $category,
        string $categoryDisplay,
        array $code,
    ): array {
        return [
            'resourceType' => 'Observation',
            'status' => 'final',
            'category' => [[
                'coding' => [SatuSehatCodes::coding('http://terminology.hl7.org/CodeSystem/observation-category', $category, $categoryDisplay)],
            ]],
            'code' => SatuSehatCodes::codeable($code),
            'subject' => ['reference' => 'Patient/'.$patientIhs],
            'encounter' => ['reference' => 'Encounter/'.$encounterId],
            'effectiveDateTime' => $this->fhirTime($at),
            'performer' => [['reference' => 'Practitioner/'.$practitionerIhs]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function conditionPayload(Diagnosis $diagnosis, string $patientIhs, string $encounterId, CarbonInterface $at): array
    {
        return [
            'resourceType' => 'Condition',
            'clinicalStatus' => SatuSehatCodes::codeable(SatuSehatCodes::coding('http://terminology.hl7.org/CodeSystem/condition-clinical', 'active', 'Active')),
            'category' => [[
                'coding' => [SatuSehatCodes::coding('http://terminology.hl7.org/CodeSystem/condition-category', 'encounter-diagnosis', 'Encounter Diagnosis')],
            ]],
            'code' => [
                'coding' => [SatuSehatCodes::coding(SatuSehatCodes::ICD10, (string) $diagnosis->code, (string) $diagnosis->description)],
                'text' => (string) $diagnosis->description,
            ],
            'subject' => ['reference' => 'Patient/'.$patientIhs],
            'encounter' => ['reference' => 'Encounter/'.$encounterId],
            'recordedDate' => $this->fhirTime($at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function procedurePayload(
        ProcedureRecord $record,
        string $patientIhs,
        string $practitionerIhs,
        string $encounterId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): array {
        $payload = [
            'resourceType' => 'Procedure',
            'status' => 'completed',
            'code' => SatuSehatCodes::codeable(SatuSehatCodes::coding(
                SatuSehatCodes::ICD9,
                (string) $record->icd9_code,
                (string) ($record->procedure?->name ?: $record->icd9_code),
            )),
            'subject' => ['reference' => 'Patient/'.$patientIhs],
            'encounter' => ['reference' => 'Encounter/'.$encounterId],
            'performedPeriod' => [
                'start' => $this->fhirTime($start),
                'end' => $this->fhirTime($end),
            ],
            'performer' => [[
                'actor' => ['reference' => 'Practitioner/'.$practitionerIhs],
            ]],
        ];

        $tooth = SatuSehatCodes::tooth((string) $record->tooth_number);

        if ($tooth) {
            $payload['bodySite'] = [SatuSehatCodes::codeable(SatuSehatCodes::coding(SatuSehatCodes::SNOMED, $tooth[0], $tooth[1]))];
        }

        return $payload;
    }

    /**
     * Cabang memakai Organization induk yang sudah ada di SATUSEHAT.
     * Ruangan pemeriksaan didaftarkan sebagai Location bertipe Room di bawah lokasi poli cabang.
     *
     * @return array{id: string, name: string}
     */
    protected function encounterLocation(SatuSehatClient $client, Visit $visit): array
    {
        $room = $visit->room;

        if ($room && in_array($room->type, [RoomType::Poli, RoomType::Tindakan], true)) {
            return $this->ensureRoomLocation($client, $visit, $room);
        }

        if ($room?->type === RoomType::Tunggu) {
            $this->notes[] = 'Ruang tunggu tidak dipakai sebagai lokasi pemeriksaan. Encounter memakai lokasi poli cabang.';
        }

        return [
            'id' => (string) $this->branch->location_id,
            'name' => (string) ($this->branch->location_name ?: $this->branch->location_id),
        ];
    }

    /**
     * @return array{id: string, name: string}
     */
    protected function ensureRoomLocation(SatuSehatClient $client, Visit $visit, Room $room): array
    {
        $name = (string) $room->name;
        $id = $this->send(
            $client,
            'Location',
            'location:room:'.$room->id,
            $this->roomLocationPayload($visit, $room),
            $room,
        );

        return ['id' => $id, 'name' => $name];
    }

    /**
     * @return array<string, mixed>
     */
    protected function roomLocationPayload(Visit $visit, Room $room): array
    {
        $branch = $visit->branch;
        $organizationId = (string) $this->branch->organization_id;
        $name = (string) $room->name;
        $managing = ['reference' => 'Organization/'.$organizationId];

        if (filled($this->branch->organization_name)) {
            $managing['display'] = (string) $this->branch->organization_name;
        }

        $payload = [
            'resourceType' => 'Location',
            'identifier' => [[
                'system' => 'http://sys-ids.kemkes.go.id/location/'.$organizationId,
                'value' => filled($room->code) ? (string) $room->code : 'ROOM-'.$room->id,
            ]],
            'status' => $room->is_active ? 'active' : 'inactive',
            'name' => $name,
            'description' => filled($room->description)
                ? (string) $room->description
                : trim($name.', '.($room->type?->label() ?: 'Ruangan').', '.($branch?->name ?: 'Cabang')),
            'mode' => 'instance',
            'physicalType' => SatuSehatCodes::codeable(SatuSehatCodes::coding(
                'http://terminology.hl7.org/CodeSystem/location-physical-type',
                'ro',
                'Room',
            )),
            'managingOrganization' => $managing,
            'partOf' => ['reference' => 'Location/'.$this->branch->location_id],
            'extension' => [[
                'url' => 'https://fhir.kemkes.go.id/r4/StructureDefinition/LocationServiceClass',
                'valueCodeableConcept' => SatuSehatCodes::codeable(SatuSehatCodes::coding(
                    'http://terminology.kemkes.go.id/CodeSystem/locationServiceClass-Outpatient',
                    'reguler',
                    'Kelas Reguler',
                )),
            ]],
        ];

        $telecom = [];
        $phone = $room->phone ?: $branch?->phone;
        $email = $room->email ?: $branch?->email;

        if (filled($phone)) {
            $telecom[] = ['system' => 'phone', 'value' => (string) $phone, 'use' => 'work'];
        }

        if (filled($email)) {
            $telecom[] = ['system' => 'email', 'value' => (string) $email, 'use' => 'work'];
        }

        if (filled($branch?->website)) {
            $telecom[] = ['system' => 'url', 'value' => (string) $branch->website, 'use' => 'work'];
        }

        if ($telecom !== []) {
            $payload['telecom'] = $telecom;
        }

        if (filled($branch?->address)) {
            $address = [
                'use' => 'work',
                'type' => 'both',
                'line' => [(string) $branch->address],
                'country' => 'ID',
            ];

            if (filled($branch->city)) {
                $address['city'] = (string) $branch->city;
            }

            if (filled($branch->postal_code)) {
                $address['postalCode'] = (string) $branch->postal_code;
            }

            $payload['address'] = [$address];
        }

        if ($branch?->longitude !== null && $branch?->latitude !== null) {
            $payload['position'] = [
                'longitude' => (float) $branch->longitude,
                'latitude' => (float) $branch->latitude,
                'altitude' => (float) ($branch->altitude ?? 0),
            ];
        }

        return $payload;
    }

    /**
     * @param  array{id: string, name: string}  $location
     * @param  list<string>  $conditionIds
     * @return array<string, mixed>
     */
    protected function encounterPayload(
        Visit $visit,
        string $patientIhs,
        string $practitionerIhs,
        array $location,
        CarbonInterface $start,
        CarbonInterface $end,
        bool $finished,
        array $conditionIds,
    ): array {
        $arrivedEnd = $start->copy()->addMinute();
        $history = [
            [
                'status' => 'arrived',
                'period' => ['start' => $this->fhirTime($start), 'end' => $this->fhirTime($arrivedEnd)],
            ],
            [
                'status' => 'in-progress',
                'period' => [
                    'start' => $this->fhirTime($arrivedEnd),
                    'end' => $this->fhirTime($finished ? $end : $arrivedEnd->copy()->addMinutes(30)),
                ],
            ],
        ];

        if ($finished) {
            $history[] = [
                'status' => 'finished',
                'period' => [
                    'start' => $this->fhirTime($end),
                    'end' => $this->fhirTime($end->copy()->addSecond()),
                ],
            ];
        }

        $period = ['start' => $this->fhirTime($start)];

        if ($finished) {
            $period['end'] = $this->fhirTime($end->copy()->addSecond());
        }

        $referred = $visit->referrals->isNotEmpty();
        $organizationId = (string) $this->branch->organization_id;
        $payload = [
            'resourceType' => 'Encounter',
            'identifier' => [[
                'system' => 'http://sys-ids.kemkes.go.id/encounter/'.$organizationId,
                'value' => (string) $visit->id,
            ]],
            'status' => $finished ? 'finished' : 'in-progress',
            'statusHistory' => $history,
            'class' => SatuSehatCodes::coding('http://terminology.hl7.org/CodeSystem/v3-ActCode', 'AMB', 'ambulatory'),
            'subject' => ['reference' => 'Patient/'.$patientIhs],
            'participant' => [[
                'type' => [[
                    'coding' => [SatuSehatCodes::coding('http://terminology.hl7.org/CodeSystem/v3-ParticipationType', 'ATND', 'attender')],
                ]],
                'individual' => ['reference' => 'Practitioner/'.$practitionerIhs],
            ]],
            'period' => $period,
            'location' => [[
                'location' => [
                    'reference' => 'Location/'.$location['id'],
                    'display' => $location['name'],
                ],
                'period' => $period,
                'extension' => [[
                    'url' => 'https://fhir.kemkes.go.id/r4/StructureDefinition/ServiceClass',
                    'extension' => [[
                        'url' => 'value',
                        'valueCodeableConcept' => SatuSehatCodes::codeable(SatuSehatCodes::coding(
                            'http://terminology.kemkes.go.id/CodeSystem/locationServiceClass-Outpatient',
                            'reguler',
                            'Kelas Reguler',
                        )),
                    ]],
                ]],
            ]],
            'serviceProvider' => ['reference' => 'Organization/'.$organizationId],
        ];

        if ($finished) {
            $payload['hospitalization'] = [
                'dischargeDisposition' => SatuSehatCodes::codeable(SatuSehatCodes::coding(
                    'http://terminology.hl7.org/CodeSystem/discharge-disposition',
                    $referred ? 'other-hcf' : 'home',
                    $referred ? 'Other healthcare facility' : 'Home',
                )),
            ];

            $diagnosis = [];

            foreach (array_values($conditionIds) as $rank => $conditionId) {
                $diagnosis[] = [
                    'condition' => ['reference' => 'Condition/'.$conditionId],
                    'use' => SatuSehatCodes::codeable(SatuSehatCodes::coding('http://terminology.hl7.org/CodeSystem/diagnosis-role', 'DD', 'Discharge diagnosis')),
                    'rank' => $rank + 1,
                ];
            }

            if ($diagnosis !== []) {
                $payload['diagnosis'] = $diagnosis;
            } else {
                $this->notes[] = 'Kunjungan ditutup tanpa diagnosis karena belum ada ICD-10.';
            }
        }

        return $payload;
    }

    protected function periodStart(Visit $visit): CarbonInterface
    {
        $date = $visit->visit_date?->toDateString() ?: now()->toDateString();

        return Carbon::parse($date.' 08:00:00', 'Asia/Jakarta');
    }

    protected function periodEnd(Visit $visit, CarbonInterface $start): CarbonInterface
    {
        $close = $start->copy()->addHour();
        $now = now('Asia/Jakarta');

        if ($visit->visit_date?->isSameDay($now) && $now->greaterThan($close)) {
            return $now;
        }

        return $close;
    }

    protected function fhirTime(CarbonInterface $time): string
    {
        return $time->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
