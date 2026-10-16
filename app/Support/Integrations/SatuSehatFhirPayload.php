<?php

namespace App\Support\Integrations;

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

    public const MEDICATION_TYPE_SYSTEM = 'https://terminology.kemkes.go.id/CodeSystem/medication-type';

    public const KFA_SYSTEM = 'http://sys-ids.kemkes.go.id/kfa';

    /**
     * @return array{code: string, display: string}
     */
    public static function medicationTypeForItem(PrescriptionItem $item): array
    {
        if (! $item->isCompound()) {
            return ['code' => 'NC', 'display' => 'Non-compound'];
        }

        // Form racikan lokal = "dibuat N bungkus/puyer" (bagi sama rata), bukan d.t.d per dosis.
        // Tanpa flag klinis SD vs EP, hanya EP yang dapat dibuktikan dari data sumber.
        return ['code' => 'EP', 'display' => 'Divide into equal parts'];
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

            $unit = trim((string) ($ingredient->unit ?: $medicine?->unit ?: 'TAB'));
            $qty = (float) ($ingredient->quantity ?: 1);
            $pack = (float) ($item->quantity ?: 1);

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
                'strength' => [
                    'numerator' => [
                        'value' => $qty,
                        'system' => 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm',
                        'code' => strtoupper($unit) === 'MG' ? 'TAB' : strtoupper($unit),
                    ],
                    'denominator' => [
                        'value' => $pack,
                        'system' => 'http://terminology.hl7.org/CodeSystem/v3-orderableDrugForm',
                        'code' => 'PKG',
                    ],
                ],
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
