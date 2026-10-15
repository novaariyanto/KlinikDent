<?php

namespace App\Support\Integrations;

use App\Models\BranchSatuSehatSetting;
use App\Models\TenantIntegration;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SatuSehatClient
{
    /**
     * Setiap panggilan FHIR: method, URL, body request, status HTTP, dan body response.
     *
     * @var list<array{method: string, url: string, request: mixed, status: int|null, response: mixed}>
     */
    public array $exchanges = [];

    public function __construct(
        protected TenantIntegration $tenant,
        protected BranchSatuSehatSetting $branch,
    ) {}

    public function localSandbox(): bool
    {
        return (bool) $this->tenant->fake;
    }

    /**
     * @return array{ok: bool, organization_name?: string, locations: list<array{id: string, name: string}>, error?: string}
     */
    public function testConnection(): array
    {
        if (! filled($this->branch->organization_id) || ! filled($this->branch->client_id) || ! $this->branch->hasSecret()) {
            return [
                'ok' => false,
                'locations' => [],
                'error' => 'Organization ID, Client ID, dan Client Secret wajib diisi sebelum koneksi diuji.',
            ];
        }

        if ($this->localSandbox()) {
            return [
                'ok' => true,
                'organization_name' => 'Sandbox lokal',
                'locations' => [[
                    'id' => $this->sandboxLocationId(),
                    'name' => 'Poli Gigi (sandbox lokal)',
                ]],
            ];
        }

        try {
            $organization = $this->authed()->get($this->fhirUrl('Organization/'.$this->branch->organization_id));

            if (! $organization->successful() || ($organization->json('resourceType') !== 'Organization')) {
                return [
                    'ok' => false,
                    'locations' => [],
                    'error' => $this->failureMessage($organization, 'Organization tidak ditemukan di SATUSEHAT.'),
                ];
            }

            return [
                'ok' => true,
                'organization_name' => (string) ($organization->json('name') ?: $this->branch->organization_id),
                'locations' => $this->locations(),
            ];
        } catch (IntegrationException $exception) {
            return [
                'ok' => false,
                'locations' => [],
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function locations(): array
    {
        if ($this->localSandbox()) {
            return [[
                'id' => $this->sandboxLocationId(),
                'name' => 'Poli Gigi (sandbox lokal)',
            ]];
        }

        $response = $this->authed()->get($this->fhirUrl('Location'), [
            'organization' => (string) $this->branch->organization_id,
            '_count' => 50,
        ]);

        if (! $response->successful()) {
            throw new IntegrationException($this->failureMessage($response, 'Daftar lokasi gagal diambil.'));
        }

        $locations = [];

        foreach ($response->json('entry') ?? [] as $entry) {
            $resource = $entry['resource'] ?? null;

            if (! is_array($resource) || ($resource['resourceType'] ?? '') !== 'Location' || empty($resource['id'])) {
                continue;
            }

            $locations[] = [
                'id' => (string) $resource['id'],
                'name' => (string) ($resource['name'] ?: $resource['id']),
            ];
        }

        return $locations;
    }

    public function findByNik(string $resourceType, string $nik): ?string
    {
        $query = ['identifier' => SatuSehatCodes::NIK_SYSTEM.'|'.$nik];
        $url = $this->fhirUrl($resourceType);

        if ($this->localSandbox()) {
            $id = 'sandbox-'.strtolower($resourceType).'-'.substr(hash('sha256', $resourceType.'|'.$nik), 0, 12);
            $this->record('GET', $url, $query, 200, [
                'resourceType' => 'Bundle',
                'entry' => [[
                    'resource' => ['resourceType' => $resourceType, 'id' => $id],
                ]],
            ]);

            return $id;
        }

        $response = $this->authed()->get($url, $query);
        $this->record('GET', $url, $query, $response->status(), $this->responseBody($response));

        if (! $response->successful()) {
            throw new IntegrationException($this->failureMessage($response, 'Pencarian '.$resourceType.' gagal.'));
        }

        $id = $response->json('entry.0.resource.id');

        return filled($id) ? (string) $id : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, id?: string, response: array<string, mixed>, error?: string}
     */
    public function submit(string $resourceType, array $payload, ?string $existingId, string $localKey): array
    {
        if ($this->localSandbox()) {
            $id = $existingId ?: 'sandbox-'.substr(hash('sha256', $localKey), 0, 16);
            $body = [
                'mode' => 'sandbox-lokal',
                'resourceType' => $resourceType,
                'id' => $id,
            ];
            $payload['resourceType'] = $resourceType;
            if ($existingId) {
                $payload['id'] = $existingId;
            }
            $this->record($existingId ? 'PUT' : 'POST', $this->fhirUrl($existingId ? $resourceType.'/'.$existingId : $resourceType), $payload, 200, $body);

            return [
                'ok' => true,
                'id' => $id,
                'response' => $body,
            ];
        }

        try {
            $payload['resourceType'] = $resourceType;
            $request = $this->authed();

            if ($existingId) {
                $payload['id'] = $existingId;
                $url = $this->fhirUrl($resourceType.'/'.$existingId);
                $response = $request->put($url, $payload);
                $method = 'PUT';
            } else {
                $url = $this->fhirUrl($resourceType);
                $response = $request->post($url, $payload);
                $method = 'POST';
            }

            $body = $this->responseBody($response);
            $this->record($method, $url, $payload, $response->status(), $body);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'response' => $body,
                    'error' => $this->failureMessage($response, 'Pengiriman '.$resourceType.' gagal.'),
                ];
            }

            $id = (string) ($body['id'] ?? $existingId ?? '');

            if ($id === '') {
                return [
                    'ok' => false,
                    'response' => $body,
                    'error' => 'SATUSEHAT tidak mengembalikan ID '.$resourceType.'.',
                ];
            }

            return [
                'ok' => true,
                'id' => $id,
                'response' => $body,
            ];
        } catch (IntegrationException $exception) {
            $this->record('POST', $this->fhirUrl($resourceType), $payload, null, ['error' => $exception->getMessage()]);

            return [
                'ok' => false,
                'response' => ['error' => $exception->getMessage()],
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>|null  $request
     */
    protected function record(string $method, string $url, mixed $request, ?int $status, mixed $response): void
    {
        $this->exchanges[] = [
            'method' => $method,
            'url' => $url,
            'request' => $request,
            'status' => $status,
            'response' => $response,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function responseBody(Response $response): array
    {
        $body = $response->json();

        return is_array($body) ? $body : ['body' => $response->body()];
    }

    public function forgetToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    public function sandboxLocationId(): string
    {
        return 'sandbox-location-'.$this->branch->branch_id;
    }

    protected function accessToken(): string
    {
        $cached = Cache::get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = $this->http()->asForm()->post($this->tokenUrl(), [
            'client_id' => $this->branch->client_id,
            'client_secret' => $this->branch->client_secret,
        ]);

        if (! $response->successful() || ! filled($response->json('access_token'))) {
            throw new IntegrationException($this->failureMessage($response, 'Token SATUSEHAT ditolak. Periksa Client ID, Client Secret, dan environment.'));
        }

        $token = (string) $response->json('access_token');
        $ttl = max(60, ((int) $response->json('expires_in', 3600)) - 30);
        Cache::put($this->tokenCacheKey(), $token, $ttl);

        return $token;
    }

    protected function tokenUrl(): string
    {
        return $this->branch->baseUrl().'/oauth2/v1/accesstoken?grant_type=client_credentials';
    }

    protected function fhirUrl(string $path): string
    {
        return $this->branch->baseUrl().'/fhir-r4/v1/'.ltrim($path, '/');
    }

    protected function tokenCacheKey(): string
    {
        return 'satusehat.token.'.$this->branch->id.'.'.$this->branch->environment.'.'.sha1((string) $this->branch->client_id);
    }

    protected function authed(): PendingRequest
    {
        return $this->http()->withToken($this->accessToken())->asJson();
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(20)->acceptJson();
    }

    /**
     * @return array<string, mixed>
     */
    protected function failureMessage(Response $response, string $fallback): string
    {
        $diagnostic = $response->json('issue.0.diagnostics')
            ?? $response->json('issue.0.details.text');

        $detail = is_string($diagnostic) && $diagnostic !== ''
            ? $diagnostic
            : 'HTTP '.$response->status();

        return $fallback.' '.$detail;
    }
}
