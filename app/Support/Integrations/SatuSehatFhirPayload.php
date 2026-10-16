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

    public static function isReadyKind(PrescriptionItemKind|string|null $kind): bool
    {
        if ($kind instanceof PrescriptionItemKind) {
            return $kind === PrescriptionItemKind::Ready;
        }

        return $kind === null || $kind === PrescriptionItemKind::Ready->value || $kind === 'ready';
    }
}
