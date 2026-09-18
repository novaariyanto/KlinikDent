<?php

namespace App\Support\Integrations;

use App\Enums\IntegrationProvider;
use App\Models\TenantIntegration;
use App\Models\Visit;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SatuSehatClient
{
    public function __construct(protected TenantIntegration $settings)
    {
    }

    public static function forTenant(int $tenantId): self
    {
        return new self(TenantIntegration::forTenant($tenantId, IntegrationProvider::SatuSehat));
    }

    public function settings(): TenantIntegration
    {
        return $this->settings;
    }

    /**
     * @return array{ok: bool, id?: string, response: array<string, mixed>, error?: string}
     */
    public function sendEncounter(Visit $visit): array
    {
        if (! $this->settings->enabled) {
            return [
                'ok' => false,
                'response' => ['reason' => 'disabled'],
                'error' => 'Modul SATUSEHAT belum diaktifkan untuk klinik ini.',
            ];
        }

        $payload = $this->encounterPayload($visit);

        if ($this->settings->fake) {
            return [
                'ok' => true,
                'id' => 'sandbox-'.Str::uuid()->toString(),
                'response' => [
                    'mode' => 'sandbox-fake',
                    'tenant_id' => $this->settings->tenant_id,
                    'resourceType' => 'Encounter',
                    'id' => 'sandbox-local',
                ],
            ];
        }

        if (! $this->settings->hasCredential('client_id') || ! $this->settings->hasCredential('client_secret')) {
            return [
                'ok' => false,
                'response' => ['reason' => 'missing-credentials'],
                'error' => 'Kredensial SATUSEHAT klinik ini belum diisi.',
            ];
        }

        try {
            $token = $this->accessToken();
            $response = $this->http()
                ->withToken($token)
                ->post(rtrim($this->baseUrl(), '/').'/fhir-r4/v1/Encounter', $payload);

            $body = $response->json() ?? ['body' => $response->body()];

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'response' => $body,
                    'error' => 'HTTP '.$response->status(),
                ];
            }

            return [
                'ok' => true,
                'id' => (string) ($body['id'] ?? $body['data']['id'] ?? Str::uuid()),
                'response' => is_array($body) ? $body : ['body' => $body],
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'response' => ['exception' => $e->getMessage()],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function encounterPayload(Visit $visit): array
    {
        $visit->loadMissing(['patient', 'doctor', 'branch']);

        return [
            'resourceType' => 'Encounter',
            'status' => 'finished',
            'class' => ['code' => 'AMB', 'display' => 'ambulatory'],
            'period' => [
                'start' => optional($visit->visit_date)?->toDateString(),
            ],
            'subject' => [
                'display' => 'Pasien',
                'nik' => $visit->patient?->nik,
            ],
            'serviceProvider' => [
                'display' => $visit->branch?->name,
                'identifier' => $this->settings->credential('organization_id'),
            ],
            'participant' => [
                ['individual' => ['display' => $visit->doctor?->name]],
            ],
            'identifier' => [
                ['system' => 'klinikdent-visit', 'value' => (string) $visit->id],
            ],
        ];
    }

    protected function accessToken(): string
    {
        $response = $this->http()->asForm()->post(
            $this->tokenUrl(),
            [
                'client_id' => $this->settings->credential('client_id'),
                'client_secret' => $this->settings->credential('client_secret'),
            ]
        );

        $response->throw();

        return (string) ($response->json('access_token') ?? $response->json('data.access_token'));
    }

    protected function baseUrl(): string
    {
        return (string) $this->settings->credential('base_url', config('integrations.satusehat.base_url'));
    }

    protected function tokenUrl(): string
    {
        return (string) $this->settings->credential('token_url', config('integrations.satusehat.token_url'));
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(20)->acceptJson();
    }
}
