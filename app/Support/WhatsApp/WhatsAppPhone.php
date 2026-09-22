<?php

namespace App\Support\WhatsApp;

final class WhatsAppPhone
{
    public static function normalize(?string $phone): ?string
    {
        if (! filled($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '62')) {
            $normalized = $digits;
        } elseif (str_starts_with($digits, '0')) {
            $normalized = '62'.substr($digits, 1);
        } elseif (strlen($digits) >= 9 && strlen($digits) <= 13) {
            $normalized = '62'.$digits;
        } else {
            $normalized = $digits;
        }

        return self::isValid($normalized) ? $normalized : null;
    }

    public static function isValid(?string $phone): bool
    {
        if (! filled($phone)) {
            return false;
        }

        return (bool) preg_match('/^62[0-9]{8,13}$/', $phone);
    }

    public static function toLocal(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '62')) {
            $digits = '0'.substr($digits, 2);
        } elseif (! str_starts_with($digits, '0')) {
            $digits = '0'.$digits;
        }

        return preg_match('/^08[0-9]{8,13}$/', $digits) ? $digits : null;
    }
}
