<?php

namespace App\Support\Integrations;

use App\Enums\MedicationTypeCode;
use App\Enums\PrescriptionItemKind;
use App\Models\Medicine;
use App\Models\PrescriptionItem;
use App\Models\Referral;

/**
 * Builder payload FHIR untuk resource yang sebelumnya gagal validasi SATUSEHAT.
 */
final class SatuSehatFhirPayload
{
    public const MEDICATION_TYPE_URL = 'https://fhir.kemkes.go.id/r4/StructureDefinition/MedicationType';

    /** Canonical system yang diterima server SATUSEHAT (http, bukan https). */
    public const MEDICATION_TYPE_SYSTEM = 'http://terminology.kemkes.go.id/CodeSystem/medication-type';

    public const KFA_SYSTEM = 'http://sys-ids.kemkes.go.id/kfa';

    public const ORDERABLE_DRUG_FORM = 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm';

    public const KEMKES_TERMINOLOGY = 'http://terminology.kemkes.go.id';

    /** @var list<string> */
    public const ALLERGY_CATEGORIES = ['food', 'medication', 'environment', 'biologic'];

    /**
     * Mapping satuan lokal → kode OrderableDrugForm (MedicationRequest.dispenseRequest.quantity, dll).
     */
    public static function orderableDrugFormCode(?string $localUnit, bool $compoundPack = false): string
    {
        if ($compoundPack) {
            return 'PKG';
        }

        $normalized = strtolower(trim((string) $localUnit));

        return match ($normalized) {
            'tablet', 'tab' => 'TAB',
            'kapsul', 'capsule', 'cap', 'kap' => 'CAP',
            'botol', 'bottle', 'bot' => 'BOT',
            'tube' => 'TUBE',
            'ampul', 'ampule', 'amp' => 'AMP',
            'sachet', 'bungkus', 'pkg', 'pack', 'pcs' => 'PKG',
            'mg' => 'TAB',
            default => filled($normalized) && preg_match('/^[A-Z0-9]{2,12}$/', strtoupper($normalized)) === 1
                ? strtoupper($normalized)
                : 'TAB',
        };
    }

    /**
     * SimpleQuantity dengan system+code wajib (RuleNumber 10348 jika system kosong).
     *
     * @return array{value: float, unit: string, system: string, code: string}
     */
    public static function simpleQuantity(float $value, ?string $localUnit, bool $compoundPack = false): array
    {
        $code = self::orderableDrugFormCode($localUnit, $compoundPack);

        return [
            'value' => $value,
            'unit' => $code,
            'system' => self::ORDERABLE_DRUG_FORM,
            'code' => $code,
        ];
    }

    /**
     * @return array{code: string, display: string}
     */
    public static function medicationTypeForItem(PrescriptionItem $item): array
    {
        $stored = $item->medication_type;

        if ($stored instanceof MedicationTypeCode) {
            return ['code' => $stored->value, 'display' => $stored->display()];
        }

        if (! $item->isCompound()) {
            return ['code' => MedicationTypeCode::NonCompound->value, 'display' => MedicationTypeCode::NonCompound->display()];
        }

        // Fallback racikan lama tanpa kolom: EP (jumlah bungkus).
        return ['code' => MedicationTypeCode::EqualParts->value, 'display' => MedicationTypeCode::EqualParts->display()];
    }

    /**
     * @return array<string, mixed>
     */
    public static function medicationTypeExtension(string $code, string $display): array
    {
        return [
            'url' => self::MEDICATION_TYPE_URL,
            'valueCodeableConcept' => [
                'coding' => [[
                    'system' => self::MEDICATION_TYPE_SYSTEM,
                    'code' => $code,
                    'display' => $display,
                ]],
            ],
        ];
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}|null payload + local_key, atau null jika data tidak cukup
     */
    public static function medicationFromItem(PrescriptionItem $item, string $organizationId): ?array
    {
        $type = self::medicationTypeForItem($item);

        if ($item->isCompound()) {
            return self::medicationCompound($item, $organizationId, $type);
        }

        return self::medicationReady($item, $organizationId, $type);
    }

    /**
     * @param  array{code: string, display: string}  $type
     * @return array{0: array<string, mixed>, 1: string}|null
     */
    protected static function medicationReady(PrescriptionItem $item, string $organizationId, array $type): ?array
    {
        $medicine = $item->medicine;

        if (! $medicine instanceof Medicine || ! filled($medicine->kfa_code)) {
            return null;
        }

        $payload = [
            'resourceType' => 'Medication',
            'meta' => [
                'profile' => ['https://fhir.kemkes.go.id/r4/StructureDefinition/Medication'],
            ],
            'identifier' => [[
                'system' => 'http://sys-ids.kemkes.go.id/medication/'.$organizationId,
                'use' => 'official',
                'value' => (string) $medicine->id,
            ]],
            'code' => [
                'coding' => [[
                    'system' => self::KFA_SYSTEM,
                    'code' => (string) $medicine->kfa_code,
                    'display' => (string) $medicine->name,
                ]],
                'text' => (string) $medicine->name,
            ],
            'status' => 'active',
            'extension' => [
                self::medicationTypeExtension($type['code'], $type['display']),
            ],
        ];

        return [$payload, 'medication:'.$medicine->id];
    }

    /**
     * @param  array{code: string, display: string}  $type
     * @return array{0: array<string, mixed>, 1: string}|null
     */
    protected static function medicationCompound(PrescriptionItem $item, string $organizationId, array $type): ?array
    {
        $ingredients = [];

        foreach ($item->ingredients as $ingredient) {
            $medicine = $ingredient->medicine;
            $kfa = $medicine?->kfa_code;

            if (! filled($kfa)) {
                return null;
            }

            $formCode = self::orderableDrugFormCode((string) ($ingredient->unit ?: $medicine?->unit ?: 'tablet'));
            $qty = (float) ($ingredient->quantity ?: 1);
            $pack = (float) ($item->quantity ?: 1);
            $isDtd = ($type['code'] ?? '') === MedicationTypeCode::GiveSuchDoses->value;

            if ($isDtd) {
                $strengthValue = $ingredient->strength_value;
                $strengthUnit = strtolower(trim((string) ($ingredient->strength_unit ?: '')));

                if ($strengthValue === null || $strengthUnit === '') {
                    return null;
                }

                $strength = [
                    'numerator' => [
                        'value' => (float) $strengthValue,
                        'system' => 'http://unitsofmeasure.org',
                        'code' => $strengthUnit,
                    ],
                    'denominator' => [
                        'value' => 1,
                        'system' => self::ORDERABLE_DRUG_FORM,
                        'code' => $formCode,
                    ],
                ];
            } else {
                $strength = [
                    'numerator' => [
                        'value' => $qty,
                        'system' => self::ORDERABLE_DRUG_FORM,
                        'code' => $formCode,
                    ],
                    'denominator' => [
                        'value' => $pack,
                        'system' => self::ORDERABLE_DRUG_FORM,
                        'code' => 'PKG',
                    ],
                ];
            }

            $ingredients[] = [
                'itemCodeableConcept' => [
                    'coding' => [[
                        'system' => self::KFA_SYSTEM,
                        'code' => (string) $kfa,
                        'display' => (string) ($medicine?->name ?: $ingredient->name),
                    ]],
                    'text' => (string) ($ingredient->name ?: $medicine?->name),
                ],
                'isActive' => true,
                'strength' => $strength,
            ];
        }

        if ($ingredients === []) {
            return null;
        }

        $payload = [
            'resourceType' => 'Medication',
            'meta' => [
                'profile' => ['https://fhir.kemkes.go.id/r4/StructureDefinition/Medication'],
            ],
            'identifier' => [[
                'system' => 'http://sys-ids.kemkes.go.id/medication/'.$organizationId,
                'use' => 'official',
                'value' => 'compound-'.$item->id,
            ]],
            'status' => 'active',
            'ingredient' => $ingredients,
            'extension' => [
                self::medicationTypeExtension($type['code'], $type['display']),
            ],
        ];

        return [$payload, 'medication:compound:'.$item->id];
    }

    /**
     * Kode layanan untuk rujukan klinik gigi (bukan permintaan lab/radiologi).
     *
     * @return array{system: string, code: string, display: string}
     */
    public static function dentalReferralCoding(): array
    {
        return SatuSehatCodes::coding(
            SatuSehatCodes::SNOMED,
            '103697008',
            'Patient referral for dental care',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function serviceRequestReferral(
        Referral $referral,
        string $organizationId,
        string $patientIhs,
        string $practitionerIhs,
        string $encounterId,
        string $authoredOn,
    ): array {
        $reason = trim((string) ($referral->reason ?: ''));
        $payload = [
            'resourceType' => 'ServiceRequest',
            'identifier' => [[
                'system' => 'http://sys-ids.kemkes.go.id/servicerequest/'.$organizationId,
                'use' => 'official',
                'value' => (string) $referral->id,
            ]],
            'status' => 'active',
            'intent' => 'order',
            'priority' => 'routine',
            'category' => [[
                'coding' => [SatuSehatCodes::coding(
                    SatuSehatCodes::SNOMED,
                    '3457005',
                    'Patient referral',
                )],
            ]],
            'code' => [
                'coding' => [self::dentalReferralCoding()],
                'text' => $reason !== '' ? $reason : 'Rujukan pelayanan gigi',
            ],
            'subject' => ['reference' => 'Patient/'.$patientIhs],
            'encounter' => ['reference' => 'Encounter/'.$encounterId],
            'authoredOn' => $authoredOn,
            'requester' => ['reference' => 'Practitioner/'.$practitionerIhs],
        ];

        if (filled($referral->referred_to)) {
            $payload['performer'] = [[
                'display' => (string) $referral->referred_to,
            ]];
        }

        if (filled($referral->notes)) {
            $payload['note'] = [['text' => (string) $referral->notes]];
        }

        if ($reason !== '') {
            $payload['reasonCode'] = [[
                'text' => $reason,
            ]];
        }

        return $payload;
    }

    /**
     * Riwayat alergi. Null bila belum ditanyakan, tidak diketahui, atau belum cukup untuk dikirim.
     * Status kosong tidak dianggap "tidak ada alergi".
     *
     * @param  array<string, mixed>  $history
     * @return array{payload: array<string, mixed>|null, note: string|null}
     */
    public static function allergyIntolerance(
        array $history,
        string $organizationId,
        string $localValue,
        string $patientIhs,
        string $practitionerIhs,
        string $encounterId,
        string $recordedAt,
    ): array {
        $status = (string) ($history['allergy_status'] ?? '');

        if ($status === '' || $status === 'not_asked') {
            return ['payload' => null, 'note' => null];
        }

        if ($status === 'unknown') {
            return [
                'payload' => null,
                'note' => 'Alergi berstatus tidak diketahui. Tidak dikirim sebagai "tidak ada alergi".',
            ];
        }

        if ($status === 'none_confirmed') {
            $payload = self::allergyBase($organizationId, $localValue, $patientIhs, $practitionerIhs, $encounterId, $recordedAt, 'active');
            $payload['code'] = SatuSehatCodes::codeable(SatuSehatCodes::coding(
                SatuSehatCodes::SNOMED,
                '716186003',
                'No known allergy',
            ));

            return ['payload' => $payload, 'note' => null];
        }

        if ($status !== 'present') {
            return [
                'payload' => null,
                'note' => 'Status alergi "'.$status.'" tidak dikirim karena bukan nilai yang dikenali.',
            ];
        }

        $substance = trim((string) ($history['allergy_detail'] ?? ''));

        if ($substance === '') {
            return [
                'payload' => null,
                'note' => 'Alergi ada, tetapi zat pemicu kosong sehingga AllergyIntolerance tidak dikirim.',
            ];
        }

        $category = (string) ($history['allergy_category'] ?? '');
        $note = null;

        if (! in_array($category, self::ALLERGY_CATEGORIES, true)) {
            $category = 'medication';
            $note = 'Kategori alergi tidak diisi. Dikirim sebagai medication agar pencatatan alergi yang sudah ada tetap terkirim.';
        }

        $clinical = (string) ($history['allergy_clinical_status'] ?? 'active');

        if (! in_array($clinical, ['active', 'inactive', 'resolved'], true)) {
            $clinical = 'active';
        }

        $payload = self::allergyBase($organizationId, $localValue, $patientIhs, $practitionerIhs, $encounterId, $recordedAt, $clinical);
        $payload['category'] = [$category];
        $payload['code'] = ['text' => $substance];

        $type = (string) ($history['allergy_type'] ?? '');

        if (in_array($type, ['allergy', 'intolerance'], true)) {
            $payload['type'] = $type;
        }

        $reaction = trim((string) ($history['allergy_reaction'] ?? ''));

        if ($reaction !== '') {
            $event = [
                'manifestation' => [['text' => $reaction]],
            ];
            $severity = (string) ($history['allergy_severity'] ?? '');

            if (in_array($severity, ['mild', 'moderate', 'severe'], true)) {
                $event['severity'] = $severity;
            }

            $payload['reaction'] = [$event];
        }

        $clinicalNote = trim((string) ($history['allergy_note'] ?? ''));

        if ($clinicalNote !== '') {
            $payload['note'] = [['text' => $clinicalNote]];
        }

        return ['payload' => $payload, 'note' => $note];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function allergyBase(
        string $organizationId,
        string $localValue,
        string $patientIhs,
        string $practitionerIhs,
        string $encounterId,
        string $recordedAt,
        string $clinicalStatus,
    ): array {
        $display = match ($clinicalStatus) {
            'inactive' => 'Inactive',
            'resolved' => 'Resolved',
            default => 'Active',
        };

        return [
            'resourceType' => 'AllergyIntolerance',
            'identifier' => [[
                'system' => 'http://sys-ids.kemkes.go.id/allergy/'.$organizationId,
                'use' => 'official',
                'value' => $localValue,
            ]],
            'clinicalStatus' => SatuSehatCodes::codeable(SatuSehatCodes::coding(
                'http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical',
                $clinicalStatus,
                $display,
            )),
            'verificationStatus' => SatuSehatCodes::codeable(SatuSehatCodes::coding(
                'http://terminology.hl7.org/CodeSystem/allergyintolerance-verification',
                'confirmed',
                'Confirmed',
            )),
            'patient' => ['reference' => 'Patient/'.$patientIhs],
            'encounter' => ['reference' => 'Encounter/'.$encounterId],
            'recordedDate' => $recordedAt,
            'recorder' => ['reference' => 'Practitioner/'.$practitionerIhs],
        ];
    }

    /**
     * Rencana rawat jalan. Null bila tidak ada narasi rencana.
     *
     * @param  list<string>  $activities
     * @param  list<string>  $conditionIds
     * @return array<string, mixed>|null
     */
    public static function carePlan(
        string $organizationId,
        string $localValue,
        string $patientIhs,
        string $practitionerIhs,
        string $encounterId,
        string $createdAt,
        string $status,
        string $description,
        array $activities,
        array $conditionIds,
        ?string $periodStart = null,
        ?string $periodEnd = null,
    ): ?array {
        $description = trim($description);

        if ($description === '') {
            return null;
        }

        if (! in_array($status, ['active', 'completed', 'on-hold'], true)) {
            $status = 'active';
        }

        $activityStatus = match ($status) {
            'completed' => 'completed',
            'on-hold' => 'on-hold',
            default => 'scheduled',
        };

        $payload = [
            'resourceType' => 'CarePlan',
            'identifier' => [[
                'system' => 'http://sys-ids.kemkes.go.id/careplan/'.$organizationId,
                'use' => 'official',
                'value' => $localValue,
            ]],
            'status' => $status,
            'intent' => 'plan',
            'category' => [[
                'coding' => [SatuSehatCodes::coding(SatuSehatCodes::SNOMED, '736271009', 'Outpatient care plan')],
            ]],
            'title' => 'Rencana Perawatan',
            'description' => $description,
            'subject' => ['reference' => 'Patient/'.$patientIhs],
            'encounter' => ['reference' => 'Encounter/'.$encounterId],
            'created' => $createdAt,
            'author' => ['reference' => 'Practitioner/'.$practitionerIhs],
        ];

        if (filled($periodStart) && filled($periodEnd)) {
            $payload['period'] = [
                'start' => $periodStart,
                'end' => $periodEnd,
            ];
        }

        $addresses = [];

        foreach ($conditionIds as $conditionId) {
            $reference = self::reference('Condition', (string) $conditionId);

            if ($reference !== null) {
                $addresses[] = $reference;
            }
        }

        if ($addresses !== []) {
            $payload['addresses'] = $addresses;
        }

        $details = [];

        foreach ($activities as $activity) {
            $text = trim((string) $activity);

            if ($text === '') {
                continue;
            }

            $details[] = [
                'detail' => [
                    'status' => $activityStatus,
                    'description' => $text,
                ],
            ];
        }

        if ($details !== []) {
            $payload['activity'] = $details;
        }

        return $payload;
    }

    /**
     * Resume medis rawat jalan. Hanya section yang punya narasi atau referensi resource yang benar-benar ada.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>|null
     */
    public static function compositionResume(array $input): ?array
    {
        $sections = [];
        $anamnesis = [];
        $chief = trim((string) ($input['chief_complaint'] ?? ''));

        if ($chief !== '') {
            $anamnesis[] = self::compositionSection(
                'Keluhan Utama',
                SatuSehatCodes::LOINC,
                '10154-3',
                'Chief complaint Narrative - Reported',
                text: $chief,
            );
        }

        $allergyEntries = self::references('AllergyIntolerance', $input['allergy_ids'] ?? []);

        if ($allergyEntries !== []) {
            $anamnesis[] = self::compositionSection(
                'Riwayat Alergi',
                SatuSehatCodes::LOINC,
                '48765-2',
                'Allergies',
                $allergyEntries,
                'Riwayat alergi pada kunjungan ini.',
            );
        }

        $medicationStatementEntries = self::references('MedicationStatement', $input['medication_statement_ids'] ?? []);

        if ($medicationStatementEntries !== []) {
            $anamnesis[] = self::compositionSection(
                'Riwayat Pengobatan',
                SatuSehatCodes::LOINC,
                '10160-0',
                'History of Medication use Narrative',
                $medicationStatementEntries,
                'Riwayat pengobatan yang disampaikan pasien.',
            );
        }

        $anamnesisText = trim((string) ($input['anamnesis'] ?? ''));

        if ($anamnesis !== [] || $anamnesisText !== '') {
            $sections[] = self::compositionSection(
                'Anamnesis',
                self::KEMKES_TERMINOLOGY,
                'TK000003',
                'Anamnesis',
                text: $anamnesisText !== '' ? $anamnesisText : null,
                children: $anamnesis,
            );
        }

        $examChildren = [];
        $vitalEntries = self::references('Observation', $input['vital_ids'] ?? []);

        if ($vitalEntries !== []) {
            $examChildren[] = self::compositionSection(
                'Tanda Vital',
                SatuSehatCodes::LOINC,
                '8716-3',
                'Vital signs',
                $vitalEntries,
                'Tanda vital kunjungan.',
            );
        }

        $examEntries = self::references('Observation', $input['exam_ids'] ?? []);

        if ($examEntries !== []) {
            $examChildren[] = self::compositionSection(
                'Pemeriksaan Fisik Head to Toe',
                SatuSehatCodes::LOINC,
                '10187-3',
                'Review of systems Narrative - Reported',
                $examEntries,
                'Pemeriksaan gigi dan mulut.',
            );
        }

        if ($examChildren !== []) {
            $sections[] = self::compositionSection(
                'Pemeriksaan Fisik',
                self::KEMKES_TERMINOLOGY,
                'TK000007',
                'Pemeriksaan Fisik',
                children: $examChildren,
            );
        }

        $planEntries = array_merge(
            self::references('CarePlan', $input['care_plan_ids'] ?? []),
            self::references('ClinicalImpression', $input['clinical_impression_ids'] ?? []),
        );

        if ($planEntries !== []) {
            $sections[] = self::compositionSection(
                'Perencanaan Perawatan',
                SatuSehatCodes::LOINC,
                '18776-5',
                'Plan of care note',
                $planEntries,
                'Rencana perawatan dan prognosis.',
            );
        }

        $conditionEntries = self::references('Condition', $input['condition_ids'] ?? []);

        if ($conditionEntries !== []) {
            $sections[] = self::compositionSection(
                'Diagnosis',
                self::KEMKES_TERMINOLOGY,
                'TK000004',
                'Diagnosis',
                children: [self::compositionSection(
                    'Diagnosis Akhir',
                    SatuSehatCodes::LOINC,
                    '78375-3',
                    'Discharge diagnosis Narrative',
                    $conditionEntries,
                    'Diagnosis kunjungan.',
                )],
            );
        }

        $procedureEntries = self::references('Procedure', $input['procedure_ids'] ?? []);

        if ($procedureEntries !== []) {
            $sections[] = self::compositionSection(
                'Tindakan/Prosedur Medis',
                self::KEMKES_TERMINOLOGY,
                'TK000005',
                'Tindakan/Prosedur Medis',
                $procedureEntries,
                'Tindakan pada kunjungan ini.',
            );
        }

        $medicationEntries = self::references('MedicationRequest', $input['medication_request_ids'] ?? []);

        if ($medicationEntries !== []) {
            $sections[] = self::compositionSection(
                'Farmasi',
                self::KEMKES_TERMINOLOGY,
                'TK000013',
                'Obat',
                children: [self::compositionSection(
                    'Obat Saat Kunjungan',
                    SatuSehatCodes::LOINC,
                    '42346-7',
                    'Medications on admission (narrative)',
                    $medicationEntries,
                    'Obat yang diresepkan pada kunjungan ini.',
                )],
            );
        }

        $educationEntries = self::references('Procedure', $input['education_ids'] ?? []);

        if ($educationEntries !== []) {
            $sections[] = self::compositionSection(
                'Edukasi',
                SatuSehatCodes::LOINC,
                '34895-3',
                'Education note',
                $educationEntries,
                'Edukasi yang diberikan kepada pasien.',
            );
        }

        $followUpEntries = array_merge(
            self::references('CarePlan', $input['care_plan_ids'] ?? []),
            self::references('ServiceRequest', $input['service_request_ids'] ?? []),
        );

        if ($followUpEntries !== []) {
            $sections[] = self::compositionSection(
                'Rencana Tindak Lanjut',
                SatuSehatCodes::LOINC,
                '8653-8',
                'Hospital Discharge instructions',
                $followUpEntries,
                'Rencana tindak lanjut kunjungan.',
            );
        }

        if ($sections === []) {
            return null;
        }

        $course = trim((string) ($input['course'] ?? ''));

        if ($course === '') {
            $course = 'Resume kunjungan rawat jalan gigi.';
        }

        $sections[] = self::compositionSection(
            'Perjalanan Kunjungan Pasien',
            SatuSehatCodes::LOINC,
            '8648-8',
            'Hospital course Narrative',
            text: $course,
        );

        $organizationId = (string) $input['organization_id'];

        return [
            'resourceType' => 'Composition',
            'identifier' => [
                'system' => 'http://sys-ids.kemkes.go.id/composition/'.$organizationId,
                'value' => (string) $input['visit_id'],
            ],
            'status' => 'final',
            'type' => SatuSehatCodes::codeable(SatuSehatCodes::coding(
                SatuSehatCodes::LOINC,
                '88645-7',
                'Outpatient hospital Discharge summary',
            )),
            'category' => [SatuSehatCodes::codeable(SatuSehatCodes::coding(
                SatuSehatCodes::LOINC,
                'LP173421-1',
                'Report',
            ))],
            'subject' => ['reference' => 'Patient/'.$input['patient_ihs']],
            'encounter' => ['reference' => 'Encounter/'.$input['encounter_id']],
            'date' => (string) $input['date'],
            'author' => [[
                'reference' => 'Practitioner/'.$input['practitioner_ihs'],
            ]],
            'title' => 'Resume Medis Rawat Jalan',
            'custodian' => ['reference' => 'Organization/'.$organizationId],
            'section' => $sections,
        ];
    }

    /**
     * @param  list<array{reference: string}>  $entries
     * @param  list<array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    protected static function compositionSection(
        string $title,
        string $system,
        string $code,
        string $display,
        array $entries = [],
        ?string $text = null,
        array $children = [],
    ): array {
        $section = [
            'title' => $title,
            'code' => SatuSehatCodes::codeable(SatuSehatCodes::coding($system, $code, $display)),
        ];

        if ($text !== null && trim($text) !== '') {
            $section['text'] = self::narrative($text);
        }

        if ($entries !== []) {
            $section['entry'] = $entries;
        }

        if ($children !== []) {
            $section['section'] = $children;
        }

        return $section;
    }

    /**
     * @param  list<mixed>  $ids
     * @return list<array{reference: string}>
     */
    protected static function references(string $type, mixed $ids): array
    {
        $references = [];

        foreach (is_array($ids) ? $ids : [] as $id) {
            $reference = self::reference($type, (string) $id);

            if ($reference !== null && ! in_array($reference, $references, true)) {
                $references[] = $reference;
            }
        }

        return $references;
    }

    /**
     * @return array{reference: string}|null
     */
    protected static function reference(string $type, string $id): ?array
    {
        $id = trim($id);

        if ($id === '' || str_contains($id, '/') || str_contains($id, ' ')) {
            return null;
        }

        return ['reference' => $type.'/'.$id];
    }

    /**
     * @return array{status: string, div: string}
     */
    protected static function narrative(string $text): array
    {
        $safe = htmlspecialchars(trim($text), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return [
            'status' => 'generated',
            'div' => '<div xmlns="http://www.w3.org/1999/xhtml">'.$safe.'</div>',
        ];
    }

    public static function isReadyKind(PrescriptionItemKind|string|null $kind): bool
    {
        if ($kind instanceof PrescriptionItemKind) {
            return $kind === PrescriptionItemKind::Ready;
        }

        return $kind === null || $kind === PrescriptionItemKind::Ready->value || $kind === 'ready';
    }
}
