<?php

namespace App\Support\Integrations;

class PayloadRedactor
{
    /**
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

            if (in_array($normalized, self::$keys, true) || str_contains($normalized, 'secret') || str_contains($normalized, 'token')) {
                $redacted[$key] = '********';

                continue;
            }

            if (is_array($value)) {
                $redacted[$key] = self::redact($value);

                continue;
            }

            if (is_string($value) && preg_match('/^\d{16}$/', $value)) {
                $redacted[$key] = '********';

                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }
}
