<?php

namespace App\Support\Integrations;

use App\Enums\RoomType;
use App\Enums\SatuSehatStatus;
use App\Enums\VisitStatus;
use App\Models\Branch;
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
use App\Support\Indonesia\WilayahService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SatuSehatSync
{
    /** @var list<string> */
    protected array $notes = [];

    /**
     * @var list<array{type: string, local_key: string, error: string, stage: string, element: ?string, http_status: ?int}>
     */
    protected array $failures = [];

    /**
     * Resource yang diwajibkan untuk skenario kunjungan ini dan gagal dikirim.
     *
     * @var list<string>
     */
    protected array $requiredFailures = [];

    /**
     * ID SATUSEHAT yang berhasil pada proses ini, dikelompokkan untuk referensi Composition.
     *
     * @var array<string, list<string>>
     */
    protected array $linked = [];

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
     * @return array{ok: bool, partial?: bool, retryable?: bool, id?: string, response: array<string, mixed>, error?: string}
     */
    public function run(Visit $visit): array
    {
        $this->notes = [];
        $this->failures = [];
        $this->requiredFailures = [];
        $this->linked = [];
        $visit->loadMissing([
            'patient',
            'doctor.doctorProfile',
            'diagnoses',
            'procedureRecords.procedure',
            'medicalRecord',
            'odontogramLogs',
            'referrals',
            'prescriptions.items.medicine',
            'prescriptions.items.ingredients.medicine',
            'room',
            'branch',
        ]);

        $blocked = $this->blockedReason($visit);

        if ($blocked !== null) {
            return [
                'ok' => false,
                'partial' => false,
                'response' => ['skipped' => $this->notes],
                'error' => $blocked,
            ];
        }

        $client = new SatuSehatClient($this->tenant, $this->branch);
        $encounterId = null;
        $locationId = null;

        try {
            $patientIhs = $this->patientIhs($client, $visit->patient);
            $practitionerIhs = $this->practitionerIhs($client, $visit->doctor->doctorProfile);
            $start = $this->periodStart($visit);
            $end = $this->periodEnd($visit, $start);

            if ($start->greaterThan($end)) {
                throw IntegrationException::permanent('Waktu mulai kunjungan lebih besar dari waktu selesai. Periksa arrived_at dan completed_at.');
            }

            $location = $this->encounterLocation($client, $visit);
            $locationId = $location['id'];
            $encounterId = $this->send(
                $client,
                'Encounter',
                'encounter:'.$visit->id,
                $this->encounterPayload($visit, $patientIhs, $practitionerIhs, $location, $start, $end, false, []),
                $visit,
            );

            $conditionIds = [];

            foreach ($visit->diagnoses as $diagnosis) {
                $id = $this->trySend(
                    $client,
                    'Condition',
                    'condition:'.$diagnosis->id,
                    $this->conditionPayload($diagnosis, $patientIhs, $encounterId, $start),
                    $diagnosis,
                    required: true,
                );

                if ($id !== null) {
                    $conditionIds[] = $id;
                }
            }

            foreach ($visit->procedureRecords as $record) {
                if (! filled($record->icd9_code)) {
                    $this->notes[] = 'Tindakan #'.$record->id.' tidak dikirim karena kode ICD-9-CM kosong.';

                    continue;
                }

                $this->trySend(
                    $client,
                    'Procedure',
                    'procedure:'.$record->id,
                    $this->procedurePayload($record, $patientIhs, $practitionerIhs, $encounterId, $start, $end),
                    $record,
                    required: true,
                    bucket: 'procedure',
                );
            }

            $this->sendObservations($client, $visit, $patientIhs, $practitionerIhs, $encounterId, $start);
            $this->sendAnamnesisObservations($client, $visit, $patientIhs, $practitionerIhs, $encounterId, $start);
            $this->sendOhisObservations($client, $visit, $patientIhs, $practitionerIhs, $encounterId, $start);
            $this->sendAllergyAndMedicationHistory($client, $visit, $patientIhs, $practitionerIhs, $encounterId, $start);
            $this->sendPharmacy($client, $visit, $patientIhs, $practitionerIhs, $encounterId, $start, $end);
            $this->sendReferrals($client, $visit, $patientIhs, $practitionerIhs, $encounterId, $start);
            $this->sendEducationAndPrognosis($client, $visit, $patientIhs, $practitionerIhs, $encounterId, $start, $end);

            if ($this->requiredFailures !== []) {
                return [
                    'ok' => false,
                    'partial' => true,
                    'id' => $encounterId,
                    'response' => $this->resultPayload($client, $encounterId, $locationId),
                    'error' => 'Sinkronisasi sebagian: '.implode('; ', $this->requiredFailures).'. Encounter belum ditutup (finished).',
                ];
            }

            $this->sendCarePlan($client, $visit, $patientIhs, $practitionerIhs, $encounterId, $start, $conditionIds);
            $this->sendComposition($client, $visit, $patientIhs, $practitionerIhs, $encounterId, $end, $conditionIds);

            if ($this->requiredFailures !== []) {
                return [
                    'ok' => false,
                    'partial' => true,
                    'id' => $encounterId,
                    'response' => $this->resultPayload($client, $encounterId, $locationId),
                    'error' => 'Sinkronisasi sebagian: '.implode('; ', $this->requiredFailures).'. Encounter belum ditutup (finished).',
                ];
            }

            $encounterId = $this->send(
                $client,
                'Encounter',
                'encounter:'.$visit->id,
                $this->encounterPayload($visit, $patientIhs, $practitionerIhs, $location, $start, $end, true, $conditionIds),
                $visit,
            );

            return [
                'ok' => true,
                'partial' => false,
                'id' => $encounterId,
                'response' => $this->resultPayload($client, $encounterId, $locationId),
            ];
        } catch (IntegrationException $exception) {
            $partial = $encounterId !== null || $this->failures !== [];

            return [
                'ok' => false,
                'partial' => $partial,
                'retryable' => $exception->retryable,
                'id' => $encounterId ?? null,
                'response' => $this->resultPayload($client, $encounterId, $locationId),
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function resultPayload(SatuSehatClient $client, ?string $encounterId, ?string $locationId): array
    {
        return [
            'encounter_id' => $encounterId,
            'organization_id' => (string) $this->branch->organization_id,
            'location_id' => $locationId,
            'skipped' => $this->notes,
            'failures' => $this->failures,
            'required_failures' => $this->requiredFailures,
            'exchanges' => $client->exchanges,
        ];
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

    public function ensurePatientIhs(Patient $patient, bool $persist = true): string
    {
        $client = new SatuSehatClient($this->tenant, $this->branch);

        try {
            return $this->patientIhs($client, $patient, $persist);
        } finally {
            $this->exchanges = $client->exchanges;
        }
    }

    public function findPatientIhsByNik(string $nik): string
    {
        $client = new SatuSehatClient($this->tenant, $this->branch);

        try {
            $id = $client->findByNik('Patient', $nik);

            if ($id === null) {
                throw new IntegrationException('NIK pasien tidak ditemukan di MPI SATUSEHAT.');
            }

            return $id;
        } finally {
            $this->exchanges = $client->exchanges;
        }
    }

    /**
     * Kode administratif Kemendagri tanpa titik (format SATUSEHAT).
     *
     * @return list<array{url: string, valueCode: string}>
     */
    protected function patientAdministrativeCodes(Patient $patient): array
    {
        $wilayah = app(WilayahService::class);

        return [
            ['url' => 'province', 'valueCode' => (string) ($wilayah->toAdministrativeCode($patient->province_code) ?: $patient->province_code)],
            ['url' => 'city', 'valueCode' => (string) ($wilayah->toAdministrativeCode($patient->city_code) ?: $patient->city_code)],
            ['url' => 'district', 'valueCode' => (string) ($wilayah->toAdministrativeCode($patient->district_code) ?: $patient->district_code)],
            ['url' => 'village', 'valueCode' => (string) ($wilayah->toAdministrativeCode($patient->village_code) ?: $patient->village_code)],
            ['url' => 'rt', 'valueCode' => (string) $patient->rt],
            ['url' => 'rw', 'valueCode' => (string) $patient->rw],
        ];
    }

    protected function patientIhs(SatuSehatClient $client, Patient $patient, bool $persist = true): string
    {
        if (filled($patient->satusehat_id)) {
            return (string) $patient->satusehat_id;
        }

        $id = $client->findByNik('Patient', (string) $patient->nik);

        if ($id === null) {
            $id = $this->registerMissingPatient($client, $patient);
        }

        if ($persist && $patient->exists) {
            $patient->forceFill(['satusehat_id' => $id])->save();
        } else {
            $patient->satusehat_id = $id;
        }

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

        $addressGaps = $patient->missingSatuSehatAddressFields();
        if ($addressGaps !== []) {
            $missing = array_merge($missing, $addressGaps);
        }

        if ($missing !== []) {
            throw new IntegrationException(
                'NIK pasien tidak ditemukan di MPI SATUSEHAT. Pendaftaran IHS membutuhkan '.implode(', ', $missing)
                .' sesuai KTP (kode wilayah = Master Wilayah SATUSEHAT / Kemendagri).'
            );
        }

        $client->assertMasterWilayahCodes(
            (string) $patient->province_code,
            (string) $patient->city_code,
            (string) $patient->district_code,
            (string) $patient->village_code,
        );

        $extensions = [
            [
                'url' => 'https://fhir.kemkes.go.id/r4/StructureDefinition/citizenshipStatus',
                'valueCode' => ($patient->citizenship?->value ?: 'WNI'),
            ],
        ];

        if (filled($patient->birth_place)) {
            $extensions[] = [
                'url' => 'https://fhir.kemkes.go.id/r4/StructureDefinition/birthPlace',
                'valueAddress' => [
                    'city' => (string) $patient->birth_place,
                    'country' => 'ID',
                ],
            ];
        }

        $identifiers = [[
            'use' => 'official',
            'system' => SatuSehatCodes::NIK_SYSTEM,
            'value' => (string) $patient->nik,
        ]];

        if (filled($patient->family_card_number)) {
            $identifiers[] = [
                'use' => 'official',
                'system' => 'https://fhir.kemkes.go.id/id/kk',
                'value' => (string) $patient->family_card_number,
            ];
        }

        $address = [
            'use' => 'home',
            'line' => [(string) $patient->address],
            'country' => 'ID',
            'extension' => [[
                'url' => 'https://fhir.kemkes.go.id/r4/StructureDefinition/administrativeCode',
                'extension' => $this->patientAdministrativeCodes($patient),
            ]],
        ];

        if (filled($patient->address_city)) {
            $address['city'] = (string) $patient->address_city;
        }

        if (filled($patient->address_postal_code)) {
            $address['postalCode'] = (string) $patient->address_postal_code;
        }

        $payload = [
            'resourceType' => 'Patient',
            'meta' => [
                'profile' => ['https://fhir.kemkes.go.id/r4/StructureDefinition/Patient'],
            ],
            'identifier' => $identifiers,
            'active' => true,
            'name' => [[
                'use' => 'official',
                'text' => $patient->name,
            ]],
            'gender' => $patient->gender->value,
            'birthDate' => $patient->dob->format('Y-m-d'),
            'deceasedBoolean' => false,
            'multipleBirthInteger' => 0,
            'address' => [$address],
            'extension' => $extensions,
            'communication' => [[
                'language' => SatuSehatCodes::codeable(SatuSehatCodes::coding(
                    'urn:ietf:bcp:47',
                    'id-ID',
                    'Indonesian',
                )),
                'preferred' => true,
            ]],
        ];

        if ($patient->marital_status) {
            $payload['maritalStatus'] = SatuSehatCodes::codeable(SatuSehatCodes::coding(
                'http://terminology.hl7.org/CodeSystem/v3-MaritalStatus',
                $patient->marital_status->value,
                $patient->marital_status->label(),
            ));
        }

        if (filled($patient->phone)) {
            $payload['telecom'] = [[
                'system' => 'phone',
                'value' => (string) $patient->phone,
                'use' => 'mobile',
            ]];
        }

        return $this->send($client, 'Patient', 'patient:'.$patient->id, $payload, $patient);
    }

    public function ensurePractitionerIhs(Doctor $doctor): string
    {
        $client = new SatuSehatClient($this->tenant, $this->branch);

        try {
            return $this->practitionerIhs($client, $doctor);
        } finally {
            $this->exchanges = $client->exchanges;
        }
    }

    protected function practitionerIhs(SatuSehatClient $client, Doctor $doctor): string
    {
        if (filled($doctor->satusehat_id)) {
            return (string) $doctor->satusehat_id;
        }

        if (! filled($doctor->nik)) {
            throw new IntegrationException('NIK dokter belum diisi, sehingga nomor IHS tidak dapat dicari.');
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
    protected function trySend(
        SatuSehatClient $client,
        string $type,
        string $localKey,
        array $payload,
        ?Model $internal = null,
        bool $required = false,
        ?string $knownId = null,
        ?string $bucket = null,
    ): ?string {
        try {
            $id = $this->send($client, $type, $localKey, $payload, $internal, $knownId);
            $this->keep($bucket, $id);

            return $id;
        } catch (IntegrationException $exception) {
            $message = $exception->getMessage();
            $stage = str_contains($message, 'Validasi lokal gagal') ? 'local_validation' : 'remote';
            $httpStatus = null;

            foreach (array_reverse($client->exchanges) as $exchange) {
                if (($exchange['local_key'] ?? null) === $localKey && isset($exchange['status'])) {
                    $httpStatus = is_numeric($exchange['status']) ? (int) $exchange['status'] : null;
                    break;
                }
            }

            $this->failures[] = [
                'type' => $type,
                'local_key' => $localKey,
                'error' => $message,
                'stage' => $stage,
                'element' => SatuSehatPayloadValidator::fhirElementFromMessage($message),
                'http_status' => $httpStatus,
            ];

            if ($required) {
                $this->requiredFailures[] = $type.': '.$message;
            } else {
                $this->notes[] = $type.' tidak terkirim: '.$message;
            }

            return null;
        }
    }

    protected function keep(?string $bucket, ?string $id): void
    {
        if ($bucket === null || $id === null || $id === '') {
            return;
        }

        $this->linked[$bucket][] = $id;
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
        ?string $knownId = null,
        bool $useKnownId = false,
    ): string {
        $existing = SatuSehatResource::withoutGlobalScopes()
            ->where('tenant_id', $this->branch->tenant_id)
            ->where('local_key', $localKey)
            ->first();

        $remoteId = ($useKnownId && filled($knownId))
            ? (string) $knownId
            : (filled($existing?->satusehat_id) ? (string) $existing->satusehat_id : $knownId);

        // Resume: resource yang sudah synced tidak di-POST ulang (kecuali Encounter yang perlu PUT status).
        if ($remoteId
            && $existing?->sync_status === SatuSehatStatus::Synced
            && $type !== 'Encounter'
        ) {
            return (string) $remoteId;
        }

        SatuSehatPayloadValidator::assert($type, $payload, $localKey);

        $result = $client->submit($type, $payload, $remoteId, $localKey);
        $this->remember($type, $localKey, $internal, $result['id'] ?? $remoteId, $result['ok'], $result['error'] ?? null);

        if (! $result['ok']) {
            $retryable = (bool) ($result['retryable'] ?? false);
            $message = $result['error'] ?? 'Pengiriman '.$type.' gagal.';
            $element = SatuSehatPayloadValidator::fhirElementFromMessage($message);

            if ($element !== null && ! str_contains($message, $element)) {
                $message .= ' (elemen: '.$element.')';
            }

            throw $retryable
                ? IntegrationException::temporary($message)
                : IntegrationException::permanent($message);
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

        $this->trySend(
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
            required: true,
            bucket: 'exam',
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

        $this->trySend(
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
            required: true,
            bucket: 'vital',
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

        $this->trySend(
            $client,
            'Observation',
            'observation:'.$key.':'.$visit->id,
            $this->observationBase($patientIhs, $practitionerIhs, $encounterId, $at, 'exam', 'Exam', $observationCode            ) + [
                'valueCodeableConcept' => $concept,
            ],
            $visit->medicalRecord,
            required: true,
            bucket: 'exam',
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

            $this->trySend(
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
                required: true,
                bucket: 'exam',
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
            $this->trySend(
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
                required: true,
                bucket: 'exam',
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
            'issued' => $this->fhirTime($at),
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
        $this->syncBranchLocation($client, $visit);

        $room = $visit->room;

        if ($room && in_array($room->type, [RoomType::Poli, RoomType::Tindakan], true)) {
            return $this->ensureRoomLocation($client, $room);
        }

        if ($room?->type === RoomType::Tunggu) {
            $this->notes[] = 'Ruang tunggu tidak dipakai sebagai lokasi pemeriksaan. Encounter memakai lokasi poli cabang.';
        }

        return [
            'id' => (string) $this->branch->location_id,
            'name' => (string) ($this->branch->location_name ?: $visit->branch?->name ?: $this->branch->location_id),
        ];
    }

    protected function syncBranchLocation(SatuSehatClient $client, Visit $visit): void
    {
        $locationId = (string) $this->branch->location_id;
        $current = $client->read('Location', $locationId);
        unset($current['meta']);

        $current['resourceType'] = 'Location';
        $current['id'] = $locationId;
        $current['status'] = $current['status'] ?? 'active';
        $current['mode'] = $current['mode'] ?? 'instance';
        $current['name'] = (string) ($this->branch->location_name ?: ($current['name'] ?? null) ?: $visit->branch?->name ?: 'Poli');

        $organizationId = (string) $this->branch->organization_id;
        $current['managingOrganization'] = ['reference' => 'Organization/'.$organizationId];

        if (filled($this->branch->organization_name)) {
            $current['managingOrganization']['display'] = (string) $this->branch->organization_name;
        }

        $current = array_replace($current, $this->placeDetails($visit->branch));

        $this->send(
            $client,
            'Location',
            'location:branch:'.$this->branch->branch_id,
            $current,
            $visit->branch,
            $locationId,
            true,
        );
    }

    /**
     * Buat atau perbarui Location induk cabang di SATUSEHAT dari data modul Cabang.
     */
    public function ensureBranchLocation(Branch $clinicBranch): string
    {
        if ((int) $clinicBranch->id !== (int) $this->branch->branch_id) {
            throw new IntegrationException('Cabang tidak sesuai dengan pengaturan SATUSEHAT.');
        }

        if ($clinicBranch->latitude === null || $clinicBranch->longitude === null) {
            throw new IntegrationException('Lintang dan bujur cabang wajib diisi sebelum sync lokasi SATUSEHAT.');
        }

        $client = new SatuSehatClient($this->tenant, $this->branch);

        try {
            $localKey = 'location:branch:'.$clinicBranch->id;
            $organizationId = (string) $this->branch->organization_id;
            $identifier = 'BRANCH-'.$clinicBranch->id;
            $storedId = filled($this->branch->location_id)
                ? (string) $this->branch->location_id
                : SatuSehatResource::withoutGlobalScopes()
                    ->where('tenant_id', $this->branch->tenant_id)
                    ->where('local_key', $localKey)
                    ->value('satusehat_id');
            $knownId = filled($storedId)
                ? (string) $storedId
                : $client->findByIdentifier(
                    'Location',
                    'http://sys-ids.kemkes.go.id/location/'.$organizationId,
                    $identifier,
                );

            $id = $this->send(
                $client,
                'Location',
                $localKey,
                $this->branchLocationPayload($clinicBranch),
                $clinicBranch,
                $knownId ?: null,
                filled($knownId),
            );

            $this->branch->forceFill([
                'location_id' => $id,
                'location_name' => (string) $clinicBranch->name,
                'last_error' => null,
            ])->save();

            return $id;
        } finally {
            $this->exchanges = $client->exchanges;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function branchLocationPayload(Branch $clinicBranch): array
    {
        $organizationId = (string) $this->branch->organization_id;
        $name = (string) $clinicBranch->name;
        $managing = ['reference' => 'Organization/'.$organizationId];

        if (filled($this->branch->organization_name)) {
            $managing['display'] = (string) $this->branch->organization_name;
        }

        $payload = [
            'resourceType' => 'Location',
            'identifier' => [[
                'system' => 'http://sys-ids.kemkes.go.id/location/'.$organizationId,
                'value' => 'BRANCH-'.$clinicBranch->id,
            ]],
            'status' => $clinicBranch->is_active ? 'active' : 'inactive',
            'name' => $name,
            'description' => trim($name.(filled($clinicBranch->address) ? ', '.$clinicBranch->address : '')),
            'mode' => 'instance',
            'physicalType' => SatuSehatCodes::codeable(SatuSehatCodes::coding(
                'http://terminology.hl7.org/CodeSystem/location-physical-type',
                'si',
                'Site',
            )),
            'managingOrganization' => $managing,
        ];

        return array_replace($payload, $this->placeDetails($clinicBranch));
    }

    /**
     * @return array{id: string, name: string}
     */
    protected function ensureRoomLocation(SatuSehatClient $client, Room $room): array
    {
        $organizationId = (string) $this->branch->organization_id;
        $identifier = filled($room->code) ? (string) $room->code : 'ROOM-'.$room->id;
        $system = 'http://sys-ids.kemkes.go.id/location/'.$organizationId;
        $localKey = 'location:room:'.$room->id;
        $storedId = SatuSehatResource::withoutGlobalScopes()
            ->where('tenant_id', $this->branch->tenant_id)
            ->where('local_key', $localKey)
            ->value('satusehat_id');
        $knownId = filled($storedId) ? (string) $storedId : $client->findByIdentifier('Location', $system, $identifier);
        $name = (string) $room->name;
        $id = $this->send(
            $client,
            'Location',
            $localKey,
            $this->roomLocationPayload($room),
            $room,
            $knownId,
        );

        return ['id' => $id, 'name' => $name];
    }

    public function ensureRoomIhs(Room $room): string
    {
        if ($room->type !== RoomType::Poli) {
            throw new IntegrationException('IHS hanya diambil untuk ruangan poli.');
        }

        $client = new SatuSehatClient($this->tenant, $this->branch);

        try {
            $localKey = 'location:room:'.$room->id;
            $storedId = SatuSehatResource::withoutGlobalScopes()
                ->where('tenant_id', $this->branch->tenant_id)
                ->where('local_key', $localKey)
                ->value('satusehat_id');

            if (filled($storedId)) {
                return (string) $storedId;
            }

            $organizationId = (string) $this->branch->organization_id;
            $identifier = filled($room->code) ? (string) $room->code : 'ROOM-'.$room->id;
            $knownId = $client->findByIdentifier(
                'Location',
                'http://sys-ids.kemkes.go.id/location/'.$organizationId,
                $identifier,
            );

            if (filled($knownId)) {
                $this->remember('Location', $localKey, $room, $knownId, true, null);

                return $knownId;
            }

            return $this->ensureRoomLocation($client, $room)['id'];
        } finally {
            $this->exchanges = $client->exchanges;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function roomLocationPayload(Room $room): array
    {
        $room->loadMissing('branch');
        $branch = $room->branch;
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

        return array_replace($payload, $this->placeDetails($branch, $room));
    }

    /**
     * @return array<string, mixed>
     */
    protected function placeDetails(?\App\Models\Branch $branch, ?Room $room = null): array
    {
        $details = [];
        $telecom = [];
        $phone = $room?->phone ?: $branch?->phone;
        $email = $room?->email ?: $branch?->email;

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
            $details['telecom'] = $telecom;
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

            // Location.address di FHIR R4 / SATUSEHAT adalah Address tunggal (0..1), bukan array.
            $details['address'] = $address;
        }

        if ($branch?->longitude !== null && $branch?->latitude !== null) {
            $details['position'] = [
                'longitude' => (float) $branch->longitude,
                'latitude' => (float) $branch->latitude,
                'altitude' => (float) ($branch->altitude ?? 0),
            ];
        }

        return $details;
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
        $tz = config('app.timezone', 'Asia/Jakarta');

        if ($visit->arrived_at !== null) {
            return $visit->arrived_at->copy()->timezone($tz);
        }

        if ($visit->created_at !== null) {
            return $visit->created_at->copy()->timezone($tz);
        }

        $date = $visit->visit_date?->toDateString() ?: now($tz)->toDateString();

        return Carbon::parse($date.' '.now($tz)->format('H:i:s'), $tz);
    }

    protected function periodEnd(Visit $visit, CarbonInterface $start): CarbonInterface
    {
        $tz = config('app.timezone', 'Asia/Jakarta');

        if ($visit->completed_at !== null) {
            $end = $visit->completed_at->copy()->timezone($tz);

            return $end->lessThan($start) ? $start->copy()->addMinute() : $end;
        }

        $end = now($tz);

        return $end->lessThan($start) ? $start->copy()->addMinute() : $end;
    }

    protected function fhirTime(CarbonInterface $time): string
    {
        return $time->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }

    protected function sendAnamnesisObservations(
        SatuSehatClient $client,
        Visit $visit,
        string $patientIhs,
        string $practitionerIhs,
        string $encounterId,
        CarbonInterface $at,
    ): void {
        $exam = $visit->medicalRecord?->dental_exam ?? [];
        $sys = $visit->medicalRecord?->systemic_history ?? [];
        $anamnesis = array_merge($exam, $sys);

        $maps = [
            'blood_type' => [
                'code' => '883-9',
                'display' => 'ABO group [Type] in Blood',
                'values' => [
                    'A' => ['LA19710-5', 'Group A'],
                    'B' => ['LA19709-7', 'Group B'],
                    'AB' => ['LA28449-9', 'Group AB'],
                    'O' => ['LA19708-9', 'Group O'],
                ],
            ],
            'rhesus' => [
                'code' => '10331-7',
                'display' => 'Rh [Type] in Blood',
                'values' => [
                    'positive' => ['LA6576-8', 'Positive'],
                    '+' => ['LA6576-8', 'Positive'],
                    'negative' => ['LA6577-6', 'Negative'],
                    '-' => ['LA6577-6', 'Negative'],
                ],
            ],
            'pregnancy_status' => [
                'code' => '82810-3',
                'display' => 'Pregnancy status',
                'system' => SatuSehatCodes::SNOMED,
                'values' => [
                    'pregnant' => ['77386006', 'Pregnancy'],
                    'hamil' => ['77386006', 'Pregnancy'],
                    'not_pregnant' => ['60001007', 'Not pregnant'],
                    'tidak_hamil' => ['60001007', 'Not pregnant'],
                ],
            ],
        ];

        foreach ($maps as $field => $meta) {
            $raw = $anamnesis[$field] ?? null;

            if (! filled($raw)) {
                continue;
            }

            $key = (string) $raw;
            $mapped = $meta['values'][$key] ?? null;

            if ($mapped === null) {
                $this->notes[] = 'Anamnesis '.$field.' "'.$key.'" tidak dikirim karena nilai tidak dikenal.';

                continue;
            }

            $valueSystem = $meta['system'] ?? SatuSehatCodes::LOINC;
            $this->trySend(
                $client,
                'Observation',
                'observation:anamnesis:'.$visit->id.':'.$field,
                $this->observationBase(
                    $patientIhs,
                    $practitionerIhs,
                    $encounterId,
                    $at,
                    'survey',
                    'Survey',
                    SatuSehatCodes::coding(SatuSehatCodes::LOINC, $meta['code'], $meta['display']),
                ) + [
                    'valueCodeableConcept' => SatuSehatCodes::codeable(
                        SatuSehatCodes::coding($valueSystem, $mapped[0], $mapped[1])
                    ),
                ],
                $visit->medicalRecord,
            );
        }
    }

    protected function sendOhisObservations(
        SatuSehatClient $client,
        Visit $visit,
        string $patientIhs,
        string $practitionerIhs,
        string $encounterId,
        CarbonInterface $at,
    ): void {
        $ohis = $visit->medicalRecord?->dental_exam['ohis'] ?? null;

        if (! is_array($ohis)) {
            return;
        }

        $di = $ohis['debris_index'] ?? null;
        $ci = $ohis['calculus_index'] ?? null;
        $total = $ohis['total'] ?? null;
        $interpretation = $ohis['interpretation'] ?? null;

        if (! is_numeric($di) || ! is_numeric($ci) || ! is_numeric($total)) {
            if ($di !== null || $ci !== null || $total !== null) {
                $this->notes[] = 'OHIS tidak dikirim karena skor DI/CI/total belum lengkap.';
            }

            return;
        }

        $score = function (float $value, string $code, string $display, ?array $interp = null) use ($client, $visit, $patientIhs, $practitionerIhs, $encounterId, $at): void {
            $this->trySend(
                $client,
                'Observation',
                'observation:ohis:'.$visit->id.':'.$code,
                $this->observationBase(
                    $patientIhs,
                    $practitionerIhs,
                    $encounterId,
                    $at,
                    'exam',
                    'Exam',
                    SatuSehatCodes::coding(SatuSehatCodes::CLINICAL, $code, $display),
                ) + array_filter([
                    'valueQuantity' => [
                        'value' => $value,
                        'unit' => 'score',
                        'system' => 'http://unitsofmeasure.org',
                        'code' => '{score}',
                    ],
                    'interpretation' => $interp,
                ]),
                $visit->medicalRecord,
                bucket: 'exam',
            );
        };

        $score((float) $di, 'OC000056', 'Skor Total Debris Indeks');
        $score((float) $ci, 'OC000057', 'Skor Total Kalkulus Indeks');

        $interpCoding = match ((string) $interpretation) {
            'baik', 'good' => ['OI000029', 'Kondisi Gigi Baik'],
            'sedang', 'fair' => ['OI000030', 'Kondisi Gigi Cukup Baik'],
            'buruk', 'poor' => ['OI000031', 'Kondisi Gigi Buruk'],
            default => null,
        };

        $score(
            (float) $total,
            'OC000058',
            'Skor Total Oral Hygiene Index Simplified (OHIS)',
            $interpCoding
                ? [SatuSehatCodes::codeable(SatuSehatCodes::coding(SatuSehatCodes::CLINICAL, $interpCoding[0], $interpCoding[1]))]
                : null,
        );
    }

    protected function sendAllergyAndMedicationHistory(
        SatuSehatClient $client,
        Visit $visit,
        string $patientIhs,
        string $practitionerIhs,
        string $encounterId,
        CarbonInterface $at,
    ): void {
        $sys = $visit->medicalRecord?->systemic_history ?? [];
        $allergyStatus = (string) ($sys['allergy_status'] ?? '');
        $medStatus = (string) ($sys['medication_status'] ?? '');
        $medDetail = trim((string) ($sys['medication_detail'] ?? ''));
        $allergy = SatuSehatFhirPayload::allergyIntolerance(
            $sys,
            (string) $this->branch->organization_id,
            (string) $visit->id,
            $patientIhs,
            $practitionerIhs,
            $encounterId,
            $this->fhirTime($at),
        );

        if ($allergy['note'] !== null) {
            $this->notes[] = $allergy['note'];
        }

        if (is_array($allergy['payload'])) {
            $this->trySend(
                $client,
                'AllergyIntolerance',
                ($allergyStatus === 'none_confirmed' ? 'allergy:none:' : 'allergy:').$visit->id,
                $allergy['payload'],
                $visit->medicalRecord,
                required: true,
                bucket: 'allergy',
            );
        }

        if ($medStatus === 'present' && $medDetail !== '') {
            $this->trySend(
                $client,
                'MedicationStatement',
                'medstatement:'.$visit->id,
                [
                    'resourceType' => 'MedicationStatement',
                    'status' => 'active',
                    'category' => SatuSehatCodes::codeable(SatuSehatCodes::coding(
                        'http://terminology.hl7.org/CodeSystem/medication-statement-category',
                        'patientspecified',
                        'Patient Specified',
                    )),
                    'medicationCodeableConcept' => ['text' => $medDetail],
                    'subject' => ['reference' => 'Patient/'.$patientIhs],
                    'context' => ['reference' => 'Encounter/'.$encounterId],
                    'effectiveDateTime' => $this->fhirTime($at),
                    'dateAsserted' => $this->fhirTime($at),
                ],
                $visit->medicalRecord,
                bucket: 'medicationStatement',
            );
        } elseif ($medDetail !== '' && $medStatus !== 'present') {
            $this->notes[] = 'Riwayat obat rutin tidak dikirim karena status belum dikonfirmasi (pilih "Sedang memakai obat").';
        }
    }

    protected function sendPharmacy(
        SatuSehatClient $client,
        Visit $visit,
        string $patientIhs,
        string $practitionerIhs,
        string $encounterId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): void {
        $orgId = (string) $this->branch->organization_id;

        foreach ($visit->prescriptions as $prescription) {
            if (! $prescription->isSent() && ! $prescription->isFulfilled()) {
                $this->notes[] = 'Resep #'.$prescription->id.' dilewati (status draft; kirim ke farmasi dulu).';

                continue;
            }

            foreach ($prescription->items as $item) {
                $built = SatuSehatFhirPayload::medicationFromItem($item, $orgId);

                if ($built === null) {
                    $hint = $item->isCompound()
                        ? 'racikan membutuhkan bahan dengan kode KFA pada obat stok'
                        : 'kode KFA kosong';
                    $this->notes[] = 'Obat "'.$item->label().'" tidak dikirim karena '.$hint.'.';

                    continue;
                }

                [$medicationPayload, $medicationKey] = $built;

                $medicationId = $this->trySend(
                    $client,
                    'Medication',
                    $medicationKey,
                    $medicationPayload,
                    $item->isCompound() ? $item : $item->medicine,
                );

                if ($medicationId === null) {
                    continue;
                }

                $localUnit = (string) ($item->medicine?->unit ?: ($item->isCompound() ? 'bungkus' : 'tablet'));
                $dispenseQuantity = SatuSehatFhirPayload::simpleQuantity(
                    (float) ($item->quantity ?: 1),
                    $localUnit,
                    $item->isCompound(),
                );
                $requestId = $this->trySend(
                    $client,
                    'MedicationRequest',
                    'medrequest:'.$item->id,
                    [
                        'resourceType' => 'MedicationRequest',
                        'identifier' => [[
                            'system' => 'http://sys-ids.kemkes.go.id/prescription/'.$orgId,
                            'use' => 'official',
                            'value' => (string) $item->id,
                        ]],
                        'status' => $prescription->isFulfilled() ? 'completed' : 'active',
                        'intent' => 'order',
                        'category' => [[
                            'coding' => [SatuSehatCodes::coding(
                                'http://terminology.hl7.org/CodeSystem/medicationrequest-category',
                                'outpatient',
                                'Outpatient',
                            )],
                        ]],
                        'medicationReference' => ['reference' => 'Medication/'.$medicationId],
                        'subject' => ['reference' => 'Patient/'.$patientIhs],
                        'encounter' => ['reference' => 'Encounter/'.$encounterId],
                        'authoredOn' => $this->fhirTime($start),
                        'requester' => ['reference' => 'Practitioner/'.$practitionerIhs],
                        'dosageInstruction' => [[
                            'text' => trim(implode(' · ', array_filter([
                                $item->dosage,
                                $item->frequency,
                                $item->duration,
                            ]))) ?: 'Sesuai instruksi dokter',
                        ]],
                        'dispenseRequest' => [
                            'quantity' => $dispenseQuantity,
                        ],
                    ],
                    $item,
                    bucket: 'medicationRequest',
                );

                if ($requestId === null || ! $prescription->isFulfilled()) {
                    continue;
                }

                $this->trySend(
                    $client,
                    'MedicationDispense',
                    'meddispense:'.$item->id,
                    [
                        'resourceType' => 'MedicationDispense',
                        'identifier' => [[
                            'system' => 'http://sys-ids.kemkes.go.id/medicationdispense/'.$orgId,
                            'use' => 'official',
                            'value' => (string) $item->id,
                        ]],
                        'status' => 'completed',
                        'category' => SatuSehatCodes::codeable(SatuSehatCodes::coding(
                            'http://terminology.hl7.org/fhir/CodeSystem/medicationdispense-category',
                            'outpatient',
                            'Outpatient',
                        )),
                        'medicationReference' => ['reference' => 'Medication/'.$medicationId],
                        'subject' => ['reference' => 'Patient/'.$patientIhs],
                        'context' => ['reference' => 'Encounter/'.$encounterId],
                        'performer' => [[
                            'actor' => ['reference' => 'Practitioner/'.$practitionerIhs],
                        ]],
                        'authorizingPrescription' => [[
                            'reference' => 'MedicationRequest/'.$requestId,
                        ]],
                        'quantity' => $dispenseQuantity,
                        'whenHandedOver' => $this->fhirTime($end),
                    ],
                    $item,
                );
            }
        }
    }

    protected function sendReferrals(
        SatuSehatClient $client,
        Visit $visit,
        string $patientIhs,
        string $practitionerIhs,
        string $encounterId,
        CarbonInterface $at,
    ): void {
        $orgId = (string) $this->branch->organization_id;

        foreach ($visit->referrals as $referral) {
            if (! filled($referral->referred_to) && ! filled($referral->reason)) {
                $this->notes[] = 'Rujukan #'.$referral->id.' tidak dikirim karena tujuan/alasan kosong.';

                continue;
            }

            $this->trySend(
                $client,
                'ServiceRequest',
                'servicerequest:referral:'.$referral->id,
                SatuSehatFhirPayload::serviceRequestReferral(
                    $referral,
                    $orgId,
                    $patientIhs,
                    $practitionerIhs,
                    $encounterId,
                    $this->fhirTime($at),
                ),
                $referral,
                bucket: 'serviceRequest',
            );
        }
    }

    protected function sendEducationAndPrognosis(
        SatuSehatClient $client,
        Visit $visit,
        string $patientIhs,
        string $practitionerIhs,
        string $encounterId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): void {
        $plan = $visit->medicalRecord?->plan_instructions ?? [];
        $education = [];

        if (is_array($plan['items'] ?? null)) {
            $education = array_values(array_filter($plan['items']));
        } elseif (is_array($plan['education'] ?? null)) {
            $education = array_values(array_filter($plan['education']));
        }

        $educationNote = trim((string) ($plan['education_note'] ?? $plan['extra'] ?? $plan['lainnya'] ?? ''));

        if ($education !== [] || $educationNote !== '') {
            $this->trySend(
                $client,
                'Procedure',
                'procedure:education:'.$visit->id,
                [
                    'resourceType' => 'Procedure',
                    'status' => 'completed',
                    'code' => SatuSehatCodes::codeable(SatuSehatCodes::coding(
                        SatuSehatCodes::SNOMED,
                        '84635008',
                        'Disease process or condition education',
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
                    'note' => [[
                        'text' => $educationNote !== ''
                            ? $educationNote
                            : implode('; ', $education),
                    ]],
                ],
                $visit->medicalRecord,
                bucket: 'education',
            );
        }

        $prognosis = trim((string) ($plan['prognosis'] ?? $visit->medicalRecord?->clinical_notes ?? ''));
        $hasExplicitPrognosis = filled($plan['prognosis'] ?? null);

        if ($hasExplicitPrognosis && $prognosis !== '') {
            $this->trySend(
                $client,
                'ClinicalImpression',
                'clinicalimpression:'.$visit->id,
                [
                    'resourceType' => 'ClinicalImpression',
                    'status' => 'completed',
                    'description' => $prognosis,
                    'subject' => ['reference' => 'Patient/'.$patientIhs],
                    'encounter' => ['reference' => 'Encounter/'.$encounterId],
                    'effectiveDateTime' => $this->fhirTime($end),
                    'date' => $this->fhirTime($end),
                    'assessor' => ['reference' => 'Practitioner/'.$practitionerIhs],
                    'summary' => $prognosis,
                ],
                $visit->medicalRecord,
                bucket: 'prognosis',
            );
        }
    }

    /**
     * @param  list<string>  $conditionIds
     */
    protected function sendCarePlan(
        SatuSehatClient $client,
        Visit $visit,
        string $patientIhs,
        string $practitionerIhs,
        string $encounterId,
        CarbonInterface $start,
        array $conditionIds,
    ): void {
        $plan = $visit->medicalRecord?->plan_instructions ?? [];
        $labels = CareExamOptions::instructions();
        $activities = [];

        foreach ($plan['items'] ?? [] as $item) {
            $label = $labels[(string) $item] ?? null;

            if (filled($label)) {
                $activities[] = (string) $label;
            }
        }

        $extra = trim((string) ($plan['extra'] ?? ''));

        if ($extra !== '') {
            $activities[] = $extra;
        }

        $goal = trim((string) ($plan['goal'] ?? ''));
        $lines = [];

        if ($goal !== '') {
            $lines[] = 'Tujuan: '.$goal;
        }

        if ($activities !== []) {
            $lines[] = 'Instruksi: '.implode('; ', $activities);
        }

        $description = trim(implode("\n", $lines));

        if ($description === '') {
            return;
        }

        $periodEnd = null;
        $control = $visit->medicalRecord?->control_date;
        $followup = $visit->medicalRecord?->followup_date;
        $until = $control ?? $followup;

        if ($control !== null && $followup !== null && $followup->greaterThan($control)) {
            $until = $followup;
        }

        if ($until !== null) {
            $untilAt = $until->copy()->timezone(config('app.timezone', 'Asia/Jakarta'))->endOfDay();
            $periodEnd = $untilAt->greaterThan($start) ? $this->fhirTime($untilAt) : null;
        }

        $payload = SatuSehatFhirPayload::carePlan(
            (string) $this->branch->organization_id,
            (string) $visit->id,
            $patientIhs,
            $practitionerIhs,
            $encounterId,
            $this->fhirTime($start),
            (string) ($plan['status'] ?? 'active'),
            $description,
            $activities,
            $conditionIds,
            $periodEnd !== null ? $this->fhirTime($start) : null,
            $periodEnd,
        );

        if ($payload === null) {
            return;
        }

        $this->trySend(
            $client,
            'CarePlan',
            'careplan:'.$visit->id,
            $payload,
            $visit->medicalRecord,
            required: true,
            bucket: 'carePlan',
        );
    }

    /**
     * @param  list<string>  $conditionIds
     */
    protected function sendComposition(
        SatuSehatClient $client,
        Visit $visit,
        string $patientIhs,
        string $practitionerIhs,
        string $encounterId,
        CarbonInterface $end,
        array $conditionIds,
    ): void {
        $record = $visit->medicalRecord;
        $diagnoses = $visit->diagnoses
            ->map(fn ($diagnosis) => trim((string) ($diagnosis->code.' '.$diagnosis->description)))
            ->filter()
            ->implode('; ');
        $course = trim(implode('. ', array_filter([
            filled($record?->chief_complaint) ? 'Keluhan: '.$record->chief_complaint : null,
            $diagnoses !== '' ? 'Diagnosis: '.$diagnoses : null,
            filled($record?->clinical_notes) ? 'Instruksi: '.$record->clinical_notes : null,
        ])));

        $payload = SatuSehatFhirPayload::compositionResume([
            'organization_id' => (string) $this->branch->organization_id,
            'visit_id' => (string) $visit->id,
            'patient_ihs' => $patientIhs,
            'practitioner_ihs' => $practitionerIhs,
            'encounter_id' => $encounterId,
            'date' => $this->fhirTime($end),
            'chief_complaint' => (string) ($record?->chief_complaint ?? ''),
            'anamnesis' => (string) ($record?->anamnesis ?? ''),
            'course' => $course,
            'allergy_ids' => $this->linked['allergy'] ?? [],
            'medication_statement_ids' => $this->linked['medicationStatement'] ?? [],
            'vital_ids' => $this->linked['vital'] ?? [],
            'exam_ids' => $this->linked['exam'] ?? [],
            'care_plan_ids' => $this->linked['carePlan'] ?? [],
            'clinical_impression_ids' => $this->linked['prognosis'] ?? [],
            'condition_ids' => $conditionIds,
            'procedure_ids' => $this->linked['procedure'] ?? [],
            'education_ids' => $this->linked['education'] ?? [],
            'service_request_ids' => $this->linked['serviceRequest'] ?? [],
            'medication_request_ids' => $this->linked['medicationRequest'] ?? [],
        ]);

        if ($payload === null) {
            $this->notes[] = 'Resume medis tidak dikirim karena belum ada bagian klinis yang dapat direferensikan.';

            return;
        }

        $this->trySend(
            $client,
            'Composition',
            'composition:'.$visit->id,
            $payload,
            $visit->medicalRecord ?? $visit,
            required: true,
            bucket: 'composition',
        );
    }
}
