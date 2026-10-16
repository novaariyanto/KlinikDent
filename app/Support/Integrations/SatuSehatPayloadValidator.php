<?php

namespace App\Support\Integrations;

/**
 * Validasi lokal payload FHIR sebelum HTTP ke SATUSEHAT.
 */
final class SatuSehatPayloadValidator
{
    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws IntegrationException
     */
    public static function assert(string $resourceType, array $payload, string $localKey): void
    {
        $actual = (string) ($payload['resourceType'] ?? '');

        if ($actual !== '' && $actual !== $resourceType) {
            throw IntegrationException::permanent(
                'Validasi lokal gagal ['.$localKey.']: resourceType "'.$actual.'" tidak cocok dengan "'.$resourceType.'".'
            );
        }

        match ($resourceType) {
            'Medication' => self::assertMedication($payload, $localKey),
            'ServiceRequest' => self::assertServiceRequest($payload, $localKey),
            'MedicationRequest' => self::assertMedicationRequest($payload, $localKey),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected static function assertMedication(array $payload, string $localKey): void
    {
        $extension = self::medicationTypeExtension($payload);

        if ($extension === null) {
            throw IntegrationException::permanent(
                'Validasi lokal gagal ['.$localKey.']: Medication.extension MedicationType wajib diisi (elemen Medication.extension).'
            );
        }

        $coding = $extension['valueCodeableConcept']['coding'][0] ?? null;
        $code = is_array($coding) ? (string) ($coding['code'] ?? '') : '';
        $system = is_array($coding) ? (string) ($coding['system'] ?? '') : '';

        if ($system !== SatuSehatFhirPayload::MEDICATION_TYPE_SYSTEM) {
            throw IntegrationException::permanent(
                'Validasi lokal gagal ['.$localKey.']: Medication.extension MedicationType memakai system terminologi yang tidak sesuai.'
            );
        }

        if (! in_array($code, ['NC', 'SD', 'EP'], true)) {
            throw IntegrationException::permanent(
                'Validasi lokal gagal ['.$localKey.']: MedicationType "'.$code.'" tidak valid (harus NC, SD, atau EP).'
            );
        }

        if ($code === 'NC') {
            $kfa = (string) ($payload['code']['coding'][0]['code'] ?? '');
            if ($kfa === '') {
                throw IntegrationException::permanent(
                    'Validasi lokal gagal ['.$localKey.']: Medication.code.coding (KFA) wajib untuk obat non-racikan.'
                );
            }
        }

        if (in_array($code, ['SD', 'EP'], true)) {
            $ingredients = $payload['ingredient'] ?? null;
            if (! is_array($ingredients) || $ingredients === []) {
                throw IntegrationException::permanent(
                    'Validasi lokal gagal ['.$localKey.']: Medication.ingredient wajib untuk obat racikan ('.$code.').'
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    protected static function medicationTypeExtension(array $payload): ?array
    {
        $extensions = $payload['extension'] ?? null;

        if (! is_array($extensions)) {
            return null;
        }

        foreach ($extensions as $extension) {
            if (! is_array($extension)) {
                continue;
            }

            if (($extension['url'] ?? null) === SatuSehatFhirPayload::MEDICATION_TYPE_URL) {
                return $extension;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected static function assertServiceRequest(array $payload, string $localKey): void
    {
        $coding = $payload['code']['coding'][0] ?? null;

        if (! is_array($coding)
            || ! filled($coding['system'] ?? null)
            || ! filled($coding['code'] ?? null)
        ) {
            throw IntegrationException::permanent(
                'Validasi lokal gagal ['.$localKey.']: ServiceRequest.code.coding wajib (system + code).'
            );
        }

        foreach (['status', 'intent'] as $field) {
            if (! filled($payload[$field] ?? null)) {
                throw IntegrationException::permanent(
                    'Validasi lokal gagal ['.$localKey.']: ServiceRequest.'.$field.' wajib diisi.'
                );
            }
        }

        if (! filled($payload['subject']['reference'] ?? null)) {
            throw IntegrationException::permanent(
                'Validasi lokal gagal ['.$localKey.']: ServiceRequest.subject.reference wajib diisi.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected static function assertMedicationRequest(array $payload, string $localKey): void
    {
        if (! filled($payload['medicationReference']['reference'] ?? null)
            && ! filled($payload['medicationCodeableConcept']['coding'][0]['code'] ?? null)
        ) {
            throw IntegrationException::permanent(
                'Validasi lokal gagal ['.$localKey.']: MedicationRequest membutuhkan referensi Medication.'
            );
        }

        if (! filled($payload['subject']['reference'] ?? null)) {
            throw IntegrationException::permanent(
                'Validasi lokal gagal ['.$localKey.']: MedicationRequest.subject.reference wajib diisi.'
            );
        }
    }

    /**
     * Ambil path elemen FHIR dari pesan OperationOutcome SATUSEHAT.
     */
    public static function fhirElementFromMessage(?string $message): ?string
    {
        if ($message === null || $message === '') {
            return null;
        }

        if (preg_match('/Element not found:\s*([A-Za-z0-9.]+)/i', $message, $match) === 1) {
            return $match[1];
        }

        if (preg_match('/Validasi lokal gagal \[[^\]]+\]:\s*([A-Za-z0-9.]+)/i', $message, $match) === 1) {
            return $match[1];
        }

        return null;
    }
}
