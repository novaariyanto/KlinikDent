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
     * @return array<string, mixed>
     */
    public function read(string $resourceType, string $id): array
    {
        $url = $this->fhirUrl($resourceType.'/'.$id);

        if ($this->localSandbox()) {
            $body = [
                'resourceType' => $resourceType,
                'id' => $id,
                'status' => 'active',
                'name' => (string) ($this->branch->location_name ?: $id),
                'mode' => 'instance',
            ];
            $this->record('GET', $url, null, 200, $body);

            return $body;
        }

        $response = $this->authed()->get($url);
        $body = $this->responseBody($response);
        $this->record('GET', $url, null, $response->status(), $body);

        if (! $response->successful() || ($body['resourceType'] ?? '') !== $resourceType) {
            throw new IntegrationException($this->failureMessage($response, $resourceType.' tidak ditemukan di SATUSEHAT.'));
        }

        return $body;
    }

    public function findByIdentifier(string $resourceType, string $system, string $value): ?string
    {
        $query = ['identifier' => $system.'|'.$value];
        $url = $this->fhirUrl($resourceType);

        if ($this->localSandbox()) {
            $this->record('GET', $url, $query, 200, ['resourceType' => 'Bundle', 'total' => 0]);

            return null;
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
        Cache::forget($this->tokenBlockKey());
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

        if (Cache::has($this->tokenBlockKey())) {
            throw new IntegrationException('Permintaan token terlalu sering. SATUSEHAT hanya mengizinkan satu percobaan gagal per menit. Tunggu sebentar, lalu uji lagi.');
        }

        // POST /oauth2/v1/accesstoken?grant_type=client_credentials
        // Body application/x-www-form-urlencoded: client_id, client_secret.
        $endpoint = $this->branch->tokenUrl();
        $pending = $this->http()->asForm();

        if (! str_contains($endpoint, 'grant_type=')) {
            $pending = $pending->withQueryParameters(['grant_type' => 'client_credentials']);
        }

        $response = $pending->post($endpoint, [
            'client_id' => trim((string) $this->branch->client_id),
            'client_secret' => trim((string) $this->branch->client_secret),
        ]);

        if (! $response->successful() || ! filled($response->json('access_token'))) {
            Cache::put($this->tokenBlockKey(), true, 60);

            throw new IntegrationException($this->failureMessage($response, 'Token SATUSEHAT ditolak. Periksa Client ID, Client Secret, dan environment.'));
        }

        $token = (string) $response->json('access_token');
        $ttl = max(60, ((int) $response->json('expires_in', 3600)) - 30);
        Cache::put($this->tokenCacheKey(), $token, $ttl);

        return $token;
    }

    protected function fhirUrl(string $path): string
    {
        return $this->branch->baseUrl().'/fhir-r4/v1/'.ltrim($path, '/');
    }

    protected function tokenCacheKey(): string
    {
        return 'satusehat.token.'.$this->branch->id.'.'.$this->branch->environment.'.'.sha1((string) $this->branch->client_id);
    }

    protected function tokenBlockKey(): string
    {
        return $this->tokenCacheKey().'.blocked';
    }

    protected function authed(): PendingRequest
    {
        return $this->http()->withToken($this->accessToken())->asJson();
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(20)
            ->acceptJson()
            ->withOptions([
                'curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                ],
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function failureMessage(Response $response, string $fallback): string
    {
        $diagnostic = $response->json('issue.0.diagnostics')
            ?? $response->json('issue.0.details.text')
            ?? $response->json('fault.faultstring')
            ?? $response->json('error_description')
            ?? $response->json('message');

        if (! is_string($diagnostic) || trim($diagnostic) === '') {
            $plain = trim(preg_replace('/\s+/', ' ', strip_tags($response->body())) ?? '');
            $diagnostic = $plain !== ''
                ? (mb_strlen($plain) > 180 ? mb_substr($plain, 0, 177).'…' : $plain)
                : 'HTTP '.$response->status();
        }

        $normalized = strtolower($diagnostic);

        if ($response->status() === 429 || str_contains($normalized, 'rate limit')) {
            return 'Permintaan token terlalu sering. SATUSEHAT hanya mengizinkan satu percobaan gagal per menit. Tunggu sebentar, lalu uji lagi.';
        }

        if (str_contains($normalized, 'unacceptable') || str_contains($normalized, 'not able to be authenticated')) {
            return 'Client ID atau Client Secret ditolak. Isi ulang keduanya dari menu Kode Akses API, dan pilih environment yang sama (Sandbox atau Production).';
        }

        if ($response->status() === 403 && ! is_array($response->json('issue')) && ! is_array($response->json('fault'))) {
            return 'Server hosting menolak koneksi keluar ke SATUSEHAT (HTTP 403) sebelum Client ID diperiksa. Izinkan HTTPS keluar ke '.$this->branch->baseUrl().', tunggu satu menit, lalu uji lagi.';
        }

        if ($response->status() === 403) {
            return 'Akses token ditolak (HTTP 403). '.$diagnostic.' Environment di aplikasi harus sama dengan halaman Kode Akses API tempat Client ID diambil.';
        }

        return $fallback.' '.$diagnostic;
    }
}
