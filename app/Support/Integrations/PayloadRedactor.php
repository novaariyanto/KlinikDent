<?php

namespace App\Support\Integrations;

class PayloadRedactor
{
    /**
     * Kunci yang selalu disamarkan (secret + PHI).
     *
     * @var list<string>
     */
    protected static array $keys = [
        'nik',
        'nokartu',
        'no_kartu',
        'bpjs_number',
        'name',
        'nama',
        'namapeserta',
        'family',
        'given',
        'text',
        'phone',
        'telecom',
        'address',
        'line',
        'city',
        'birthdate',
        'birth_date',
        'birthDate',
        'dob',
        'medical_record_number',
        'mrn',
        'nomor_rm',
        'access_token',
        'client_secret',
        'client_id',
        'secret',
        'secret_key',
        'password',
        'user_key',
        'cons_id',
        'authorization',
        'x-signature',
        'x-api-key',
        'api_key',
        'value',
    ];

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public static function redact(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $redacted = [];

        foreach ($payload as $key => $value) {
            $normalized = strtolower((string) $key);

            if (self::shouldRedactKey($normalized)) {
                $redacted[$key] = '********';

                continue;
            }

            if (is_array($value)) {
                // identifier[].value sering berisi NIK — redaksi penuh pada cabang identifier.
                if ($normalized === 'identifier' || $normalized === 'name') {
                    $redacted[$key] = self::redact($value);

                    continue;
                }

                $redacted[$key] = self::redact($value);

                continue;
            }

            if (is_string($value)) {
                $redacted[$key] = self::maskSensitiveString($value);

                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }

    protected static function maskSensitiveString(string $value): string
    {
        if (self::looksLikeNik($value)) {
            return '********';
        }

        $masked = preg_replace('/\b\d{16}\b/', '********', $value) ?? $value;
        $masked = preg_replace('#(nik[|/])\d{16}#i', '$1********', $masked) ?? $masked;

        return $masked;
    }

    /**
     * Alias: secret dan PHI disamarkan (jangan menyimpan body FHIR mentah).
     *
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public static function redactSecrets(?array $payload): ?array
    {
        return self::redact($payload);
    }

    protected static function shouldRedactKey(string $normalized): bool
    {
        if (in_array($normalized, self::$keys, true)) {
            return true;
        }

        if (str_contains($normalized, 'secret') || str_contains($normalized, 'token')) {
            return true;
        }

        if (str_contains($normalized, 'password') || str_contains($normalized, 'authorization')) {
            return true;
        }

        return str_contains($normalized, 'nik')
            || str_contains($normalized, 'phone')
            || str_contains($normalized, 'alamat')
            || str_contains($normalized, 'address');
    }

    protected static function looksLikeNik(string $value): bool
    {
        return (bool) preg_match('/^\d{16}$/', $value);
    }
}
