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
            'AllergyIntolerance' => self::assertAllergyIntolerance($payload, $localKey),
            'CarePlan' => self::assertCarePlan($payload, $localKey),
            'Composition' => self::assertComposition($payload, $localKey),
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

        $quantity = $payload['dispenseRequest']['quantity'] ?? null;
        $system = is_array($quantity) ? (string) ($quantity['system'] ?? '') : '';
        $code = is_array($quantity) ? (string) ($quantity['code'] ?? '') : '';

        if ($system === '' || $code === '') {
            throw IntegrationException::permanent(
                'Validasi lokal gagal ['.$localKey.']: MedicationRequest.dispenseRequest.quantity wajib punya system dan code (OrderableDrugForm/UCUM/SNOMED).'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected static function assertAllergyIntolerance(array $payload, string $localKey): void
    {
        self::requireReference($payload, 'patient', 'Patient/', 'AllergyIntolerance.patient', $localKey);
        self::requireReference($payload, 'encounter', 'Encounter/', 'AllergyIntolerance.encounter', $localKey);
        self::requireReference($payload, 'recorder', 'Practitioner/', 'AllergyIntolerance.recorder', $localKey);

        $code = $payload['code'] ?? null;
        $hasCoding = is_array($code) && filled($code['coding'][0]['code'] ?? null);
        $hasText = is_array($code) && filled($code['text'] ?? null);

        if (! $hasCoding && ! $hasText) {
            throw IntegrationException::permanent(
                'Validasi lokal gagal ['.$localKey.']: AllergyIntolerance.code wajib berisi zat pemicu atau kode terminologi.'
            );
        }

        $category = $payload['category'][0] ?? null;

        if ($category !== null && ! in_array($category, SatuSehatFhirPayload::ALLERGY_CATEGORIES, true)) {
            throw IntegrationException::permanent(
                'Validasi lokal gagal ['.$localKey.']: AllergyIntolerance.category tidak valid.'
            );
        }

        foreach ($payload['reaction'] ?? [] as $reaction) {
            if (! is_array($reaction)) {
                continue;
            }

            $manifestation = $reaction['manifestation'][0] ?? null;
            $manifestationOk = is_array($manifestation)
                && (filled($manifestation['text'] ?? null) || filled($manifestation['coding'][0]['code'] ?? null));

            if (! $manifestationOk) {
                throw IntegrationException::permanent(
                    'Validasi lokal gagal ['.$localKey.']: AllergyIntolerance.reaction.manifestation wajib diisi bila reaksi dicantumkan.'
                );
            }

            $severity = $reaction['severity'] ?? null;

            if ($severity !== null && ! in_array($severity, ['mild', 'moderate', 'severe'], true)) {
                throw IntegrationException::permanent(
                    'Validasi lokal gagal ['.$localKey.']: AllergyIntolerance.reaction.severity tidak valid.'
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected static function assertCarePlan(array $payload, string $localKey): void
    {
        foreach (['status' => 'CarePlan.status', 'intent' => 'CarePlan.intent', 'title' => 'CarePlan.title', 'description' => 'CarePlan.description'] as $field => $element) {
            if (! filled($payload[$field] ?? null)) {
                throw IntegrationException::permanent(
                    'Validasi lokal gagal ['.$localKey.']: '.$element.' wajib diisi.'
                );
            }
        }

        if (($payload['intent'] ?? null) !== 'plan') {
            throw IntegrationException::permanent(
                'Validasi lokal gagal ['.$localKey.']: CarePlan.intent harus plan.'
            );
        }

        self::requireReference($payload, 'subject', 'Patient/', 'CarePlan.subject', $localKey);
        self::requireReference($payload, 'encounter', 'Encounter/', 'CarePlan.encounter', $localKey);
        self::requireReference($payload, 'author', 'Practitioner/', 'CarePlan.author', $localKey);

        $category = $payload['category'][0]['coding'][0]['code'] ?? null;

        if ($category !== null && $category !== '736271009') {
            throw IntegrationException::permanent(
                'Validasi lokal gagal ['.$localKey.']: CarePlan.category bukan rencana rawat jalan.'
            );
        }

        foreach ($payload['activity'] ?? [] as $activity) {
            if (! is_array($activity) || ! isset($activity['detail'])) {
                continue;
            }

            if (! filled($activity['detail']['status'] ?? null)) {
                throw IntegrationException::permanent(
                    'Validasi lokal gagal ['.$localKey.']: CarePlan.activity.detail.status wajib diisi.'
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected static function assertComposition(array $payload, string $localKey): void
    {
        if (($payload['status'] ?? null) !== 'final') {
            throw IntegrationException::permanent(
                'Validasi lokal gagal ['.$localKey.']: Composition.status harus final untuk resume kunjungan selesai.'
            );
        }

        if (($payload['type']['coding'][0]['code'] ?? null) !== '88645-7') {
            throw IntegrationException::permanent(
                'Validasi lokal gagal ['.$localKey.']: Composition.type wajib memakai LOINC 88645-7.'
            );
        }

        if (! filled($payload['title'] ?? null)) {
            throw IntegrationException::permanent(
                'Validasi lokal gagal ['.$localKey.']: Composition.title wajib diisi.'
            );
        }

        self::requireReference($payload, 'subject', 'Patient/', 'Composition.subject', $localKey);
        self::requireReference($payload, 'encounter', 'Encounter/', 'Composition.encounter', $localKey);

        $author = $payload['author'][0]['reference'] ?? null;

        if (! is_string($author) || ! str_starts_with($author, 'Practitioner/')) {
            throw IntegrationException::permanent(
                'Validasi lokal gagal ['.$localKey.']: Composition.author wajib merujuk Practitioner.'
            );
        }

        $sections = $payload['section'] ?? null;

        if (! is_array($sections) || $sections === []) {
            throw IntegrationException::permanent(
                'Validasi lokal gagal ['.$localKey.']: Composition.section wajib berisi minimal satu bagian.'
            );
        }

        foreach ($sections as $section) {
            if (! is_array($section) || ! self::sectionHasContent($section)) {
                throw IntegrationException::permanent(
                    'Validasi lokal gagal ['.$localKey.']: Composition.section wajib punya narasi, entri, atau subbagian.'
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected static function requireReference(array $payload, string $field, string $prefix, string $element, string $localKey): void
    {
        $reference = $payload[$field]['reference'] ?? null;

        if (! is_string($reference) || ! str_starts_with($reference, $prefix) || strlen($reference) <= strlen($prefix)) {
            throw IntegrationException::permanent(
                'Validasi lokal gagal ['.$localKey.']: '.$element.' wajib diisi.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $section
     */
    protected static function sectionHasContent(array $section): bool
    {
        if (filled($section['text']['div'] ?? null) || (is_array($section['entry'] ?? null) && $section['entry'] !== [])) {
            return true;
        }

        foreach ($section['section'] ?? [] as $child) {
            if (is_array($child) && self::sectionHasContent($child)) {
                return true;
            }
        }

        return false;
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

        foreach ([
            'Medication.extension',
            'Medication.code',
            'Medication.ingredient',
            'ServiceRequest.code',
            'ServiceRequest.subject',
            'ServiceRequest.status',
            'ServiceRequest.intent',
            'MedicationRequest.dispenseRequest.quantity',
            'MedicationRequest.subject',
            'MedicationRequest',
            'AllergyIntolerance.code',
            'AllergyIntolerance.patient',
            'AllergyIntolerance.recorder',
            'AllergyIntolerance.reaction.manifestation',
            'CarePlan.description',
            'CarePlan.subject',
            'Composition.section',
            'Composition.type',
            'Composition.author',
        ] as $element) {
            if (str_contains($message, $element)) {
                return $element;
            }
        }

        return null;
    }
}
