<?php

namespace App\Support\Integrations;

use App\Enums\BpjsMembershipStatus;
use App\Enums\IntegrationProvider;
use App\Models\TenantIntegration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class BpjsVclaimClient
{
    public function __construct(protected TenantIntegration $settings)
    {
    }

    public static function forTenant(int $tenantId): self
    {
        return new self(TenantIntegration::forTenant($tenantId, IntegrationProvider::Bpjs));
    }

    public function settings(): TenantIntegration
    {
        return $this->settings;
    }

    /**
     * @return array{ok: bool, status: BpjsMembershipStatus, response: array<string, mixed>, error?: string, number?: string}
     */
    public function checkByNik(string $nik, ?string $cardNumber = null): array
    {
        if (! $this->settings->enabled) {
            return [
                'ok' => false,
                'status' => BpjsMembershipStatus::Unknown,
                'response' => ['reason' => 'disabled'],
                'error' => 'Modul BPJS belum diaktifkan untuk klinik ini.',
            ];
        }

        $nik = preg_replace('/\D/', '', $nik) ?? '';

        if ($this->settings->fake) {
            $active = str_starts_with($nik, '1') || ($cardNumber && str_starts_with($cardNumber, '0'));

            return [
                'ok' => true,
                'status' => $active ? BpjsMembershipStatus::Active : BpjsMembershipStatus::Inactive,
                'number' => $cardNumber ?: '000'.substr($nik, -10),
                'response' => [
                    'mode' => 'sandbox-fake',
                    'tenant_id' => $this->settings->tenant_id,
                    'metaData' => ['code' => '200', 'message' => 'OK'],
                    'response' => [
                        'peserta' => [
                            'statusPeserta' => ['keterangan' => $active ? 'AKTIF' : 'TIDAK AKTIF'],
                        ],
                    ],
                ],
            ];
        }

        if (! $this->settings->hasCredential('cons_id') || ! $this->settings->hasCredential('secret_key')) {
            return [
                'ok' => false,
                'status' => BpjsMembershipStatus::Unknown,
                'response' => ['reason' => 'missing-credentials'],
                'error' => 'Kredensial BPJS klinik ini belum diisi.',
            ];
        }

        try {
            $date = now()->format('Y-m-d');
            $path = $cardNumber
                ? '/Peserta/nokartu/'.rawurlencode($cardNumber).'/tglSEP/'.$date
                : '/Peserta/nik/'.rawurlencode($nik).'/tglSEP/'.$date;

            $response = $this->signed()->get(rtrim($this->baseUrl(), '/').$path);
            $body = $response->json() ?? ['body' => $response->body()];

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'status' => BpjsMembershipStatus::Unknown,
                    'response' => is_array($body) ? $body : ['body' => $body],
                    'error' => 'HTTP '.$response->status(),
                ];
            }

            $keterangan = data_get($body, 'response.peserta.statusPeserta.keterangan')
                ?? data_get($body, 'response.statusPeserta.keterangan')
                ?? '';
            $number = (string) (data_get($body, 'response.peserta.noKartu') ?? $cardNumber ?? '');

            return [
                'ok' => true,
                'status' => str_contains(strtoupper((string) $keterangan), 'AKTIF') && ! str_contains(strtoupper((string) $keterangan), 'TIDAK')
                    ? BpjsMembershipStatus::Active
                    : BpjsMembershipStatus::Inactive,
                'number' => $number,
                'response' => is_array($body) ? $body : ['body' => $body],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => BpjsMembershipStatus::Unknown,
                'response' => ['exception' => $e->getMessage()],
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function signed(): PendingRequest
    {
        $consId = (string) $this->settings->credential('cons_id');
        $secret = (string) $this->settings->credential('secret_key');
        $timestamp = (string) time();
        $signature = base64_encode(hash_hmac('sha256', $consId.'&'.$timestamp, $secret, true));

        return Http::timeout(20)
            ->acceptJson()
            ->withHeaders([
                'X-cons-id' => $consId,
                'X-timestamp' => $timestamp,
                'X-signature' => $signature,
                'user_key' => (string) $this->settings->credential('user_key'),
            ]);
    }

    protected function baseUrl(): string
    {
        return (string) $this->settings->credential('base_url', config('integrations.bpjs.base_url'));
    }
}
