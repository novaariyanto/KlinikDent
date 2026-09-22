<?php

namespace App\Support\WhatsApp;

use App\Models\WhatsAppSetting;
use Illuminate\Support\Facades\Cache;

class WhatsAppBalanceService
{
    public const SESSION_KEY = 'whatsapp_last_topup';

    public function clientForTenant(int $tenantId): WhatsAppClient
    {
        $setting = WhatsAppSetting::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->first()
            ?? WhatsAppSetting::forTenant($tenantId);

        if (! $setting->isReady()) {
            throw new WhatsAppException('WhatsApp belum terhubung. Isi API key di pengaturan.', 'missing_credentials');
        }

        return WhatsAppClient::forSetting($setting);
    }

    /**
     * @return array{balance: int, formatted_balance: string, currency: string, messages_today: int, messages_this_month: int, has_usage: bool}
     */
    public function account(int $tenantId): array
    {
        return Cache::remember($this->accountCacheKey($tenantId), 30, function () use ($tenantId) {
            return $this->normalizeAccount($this->clientForTenant($tenantId)->getBalance());
        });
    }

    public function forgetAccountCache(int $tenantId): void
    {
        Cache::forget($this->accountCacheKey($tenantId));
    }

    /**
     * @return list<array{code: string, name: string, requires_phone: bool}>
     */
    public function channels(int $tenantId): array
    {
        return Cache::remember('whatsapp.topup-channels.v2.'.$tenantId, 60, function () use ($tenantId) {
            $channels = [];

            foreach ($this->clientForTenant($tenantId)->listTopupChannels() as $item) {
                $normalized = $this->normalizeChannel($item);

                if ($normalized !== null) {
                    $channels[] = $normalized;
                }
            }

            return $channels;
        });
    }

    /**
     * @param  array{amount: int, payment_channel?: string|null, phone?: string|null}  $input
     * @return array<string, mixed>
     */
    public function createLink(int $tenantId, array $input): array
    {
        $payload = ['amount' => (int) $input['amount']];

        if (filled($input['payment_channel'] ?? null)) {
            $payload['payment_channel'] = (string) $input['payment_channel'];
        }

        if (filled($input['phone'] ?? null)) {
            $payload['phone'] = (string) $input['phone'];
        }

        return $this->normalizeLink($this->clientForTenant($tenantId)->createTopupLink($payload));
    }

    /**
     * @return array<string, mixed>
     */
    public function link(int $tenantId, string $orderId): array
    {
        $link = $this->normalizeLink($this->clientForTenant($tenantId)->getTopupLink($orderId));

        if ($this->isPaid($link)) {
            $this->forgetAccountCache($tenantId);
        }

        return $link;
    }

    /**
     * @param  array<string, mixed>  $link
     */
    public function isPaid(array $link): bool
    {
        return in_array(strtoupper((string) ($link['status'] ?? '')), ['PAID', 'SUCCESS', 'SETTLEMENT', 'COMPLETED'], true);
    }

    public static function channelNeedsPhone(?string $code): bool
    {
        $code = strtoupper((string) $code);

        if ($code === '') {
            return true;
        }

        foreach (['WALLET', 'QRIS', 'DANA', 'OVO', 'SHOPEE', 'GOPAY', 'LINKAJA'] as $needle) {
            if (str_contains($code, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function accountCacheKey(int $tenantId): string
    {
        return 'whatsapp.balance.'.$tenantId;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{balance: int, formatted_balance: string, currency: string, messages_today: int, messages_this_month: int, has_usage: bool}
     */
    protected function normalizeAccount(array $data): array
    {
        $balance = (int) ($data['balance'] ?? 0);
        $formatted = (string) ($data['formatted_balance'] ?? '');

        if ($formatted === '') {
            $formatted = 'Rp '.number_format($balance, 0, ',', '.');
        }

        return [
            'balance' => $balance,
            'formatted_balance' => $formatted,
            'currency' => (string) ($data['currency'] ?? 'IDR'),
            'messages_today' => (int) ($data['messages_today'] ?? 0),
            'messages_this_month' => (int) ($data['messages_this_month'] ?? 0),
            'has_usage' => array_key_exists('messages_today', $data) || array_key_exists('messages_this_month', $data),
        ];
    }

    /**
     * @param  mixed  $item
     * @return array{code: string, name: string, requires_phone: bool, gateway_display_name: string}|null
     */
    protected function normalizeChannel(mixed $item): ?array
    {
        if (is_string($item) && $item !== '') {
            return [
                'code' => $item,
                'name' => $item,
                'requires_phone' => self::channelNeedsPhone($item),
                'gateway_display_name' => '',
            ];
        }

        if (! is_array($item)) {
            return null;
        }

        $code = (string) ($item['code'] ?? $item['payment_channel'] ?? $item['channel'] ?? $item['id'] ?? '');

        if ($code === '') {
            return null;
        }

        $type = strtolower((string) ($item['type'] ?? ''));
        $requires = $item['requires_phone'] ?? $item['phone_required'] ?? self::channelNeedsPhone($code);

        if (in_array($type, ['ewallet', 'wallet', 'qris'], true) || str_contains($type, 'wallet')) {
            $requires = true;
        }

        $name = (string) ($item['name'] ?? $item['label'] ?? $item['display_name'] ?? $item['gateway_display_name'] ?? $code);
        $group = match ($type) {
            'ewallet', 'wallet' => 'E-Wallet',
            'va' => 'Virtual Account',
            'qris' => 'QRIS',
            default => $type !== '' ? $type : '',
        };

        return [
            'code' => $code,
            'name' => $group !== '' ? $name.' — '.$group : $name,
            'requires_phone' => (bool) $requires,
            'gateway_display_name' => (string) ($item['gateway_display_name'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeLink(array $data): array
    {
        $amount = (int) ($data['amount'] ?? 0);
        $total = (int) ($data['total_amount'] ?? $amount);
        $admin = (int) ($data['admin_fee'] ?? max(0, $total - $amount));

        return [
            'order_id' => (string) ($data['order_id'] ?? ''),
            'status' => strtoupper((string) ($data['status'] ?? 'PENDING')),
            'gateway' => (string) ($data['gateway'] ?? ''),
            'gateway_display_name' => (string) ($data['gateway_display_name'] ?? $data['gateway'] ?? ''),
            'payment_channel' => (string) ($data['payment_channel'] ?? ''),
            'amount' => $amount,
            'admin_fee' => $admin,
            'total_amount' => $total,
            'formatted_amount' => (string) ($data['formatted_amount'] ?? ('Rp '.number_format($amount, 0, ',', '.'))),
            'formatted_total_amount' => (string) ($data['formatted_total_amount'] ?? ('Rp '.number_format($total, 0, ',', '.'))),
            'payment_url' => (string) ($data['payment_url'] ?? $data['checkout_url'] ?? ''),
            'expired_at' => $data['expired_at'] ?? null,
            'paid_at' => $data['paid_at'] ?? null,
        ];
    }
}
