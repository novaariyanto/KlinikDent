<?php

namespace App\Support\Saas;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

class PaymentGatewaySettings
{
    /**
     * @return array{
     *     enabled: bool,
     *     environment: string,
     *     merchant_id: string,
     *     client_key: string,
     *     has_server_key: bool
     * }
     */
    public static function current(): array
    {
        return [
            'enabled' => self::enabled(),
            'environment' => self::environment(),
            'merchant_id' => self::merchantId(),
            'client_key' => self::clientKey(),
            'has_server_key' => filled(self::serverKey()),
        ];
    }

    public static function enabled(): bool
    {
        $value = Setting::getValue('midtrans_enabled');

        if ($value === null) {
            return (bool) config('services.midtrans.enabled', false);
        }

        return $value === '1' || $value === 1 || $value === true;
    }

    public static function isProduction(): bool
    {
        return self::environment() === 'production';
    }

    public static function environment(): string
    {
        $value = Setting::getValue('midtrans_environment');

        if (in_array($value, ['sandbox', 'production'], true)) {
            return $value;
        }

        return config('services.midtrans.is_production') ? 'production' : 'sandbox';
    }

    public static function merchantId(): string
    {
        return (string) (Setting::getValue('midtrans_merchant_id') ?: config('services.midtrans.merchant_id', ''));
    }

    public static function clientKey(): string
    {
        return (string) (Setting::getValue('midtrans_client_key') ?: config('services.midtrans.client_key', ''));
    }

    public static function serverKey(): string
    {
        $value = Setting::getValue('midtrans_server_key');

        if ($value) {
            try {
                $value = Crypt::decryptString((string) $value);
            } catch (\Throwable) {
                // Stored value may still be plaintext.
            }

            return preg_replace('/\s+/', '', trim((string) $value)) ?: '';
        }

        return (string) config('services.midtrans.server_key', '');
    }

    public static function isReady(): bool
    {
        return self::enabled() && self::clientKey() !== '' && self::serverKey() !== '';
    }

    public static function snapBaseUrl(): string
    {
        return self::isProduction()
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';
    }

    public static function apiBaseUrl(): string
    {
        return self::isProduction()
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';
    }

    public static function snapScriptUrl(): string
    {
        return self::snapBaseUrl().'/snap/snap.js';
    }

    /**
     * @param  array{enabled: bool, environment: string, merchant_id: string, client_key: string, server_key?: string|null}  $data
     */
    public static function save(array $data): void
    {
        Setting::setValue('midtrans_enabled', ! empty($data['enabled']) ? '1' : '0');
        Setting::setValue('midtrans_environment', $data['environment']);
        Setting::setValue('midtrans_merchant_id', $data['merchant_id']);
        Setting::setValue('midtrans_client_key', $data['client_key']);

        if (filled($data['server_key'] ?? null)) {
            Setting::setValue('midtrans_server_key', Crypt::encryptString((string) $data['server_key']));
        }
    }
}
