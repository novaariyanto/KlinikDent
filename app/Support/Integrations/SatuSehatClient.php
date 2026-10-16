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

    /**
     * Ambil lokasi dari daftar organisasi, atau validasi ID yang diisi manual.
     *
     * @return array{id: string, name: string}
     */
    public function resolveLocation(string $locationId): array
    {
        $locationId = trim($locationId);

        if ($locationId === '') {
            throw new IntegrationException('Location ID wajib diisi.');
        }

        foreach ($this->locations() as $location) {
            if ($location['id'] === $locationId) {
                return $location;
            }
        }

        $resource = $this->read('Location', $locationId);
        $reference = (string) data_get($resource, 'managingOrganization.reference', '');
        $orgId = (string) $this->branch->organization_id;

        if ($orgId !== '' && $reference !== '' && ! str_ends_with($reference, '/'.$orgId) && $reference !== $orgId) {
            throw new IntegrationException('Location itu tidak dikelola oleh Organization cabang ini.');
        }

        return [
            'id' => (string) ($resource['id'] ?? $locationId),
            'name' => (string) ($resource['name'] ?? $locationId),
        ];
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
            $this->record($existingId ? 'PUT' : 'POST', $this->fhirUrl($existingId ? $resourceType.'/'.$existingId : $resourceType), $payload, 200, $body, $localKey);

            return [
                'ok' => true,
                'id' => $id,
                'local_key' => $localKey,
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
            $this->record($method, $url, $payload, $response->status(), $body, $localKey);

            if (! $response->successful()) {
                $status = $response->status();
                $retryable = $status === 429 || $status >= 500;

                return [
                    'ok' => false,
                    'retryable' => $retryable,
                    'http_status' => $status,
                    'local_key' => $localKey,
                    'response' => $body,
                    'error' => $this->failureMessage($response, 'Pengiriman '.$resourceType.' gagal.'),
                ];
            }

            $id = (string) ($body['id'] ?? $existingId ?? '');

            if ($id === '') {
                return [
                    'ok' => false,
                    'http_status' => $response->status(),
                    'local_key' => $localKey,
                    'response' => $body,
                    'error' => 'SATUSEHAT tidak mengembalikan ID '.$resourceType.'.',
                ];
            }

            return [
                'ok' => true,
                'id' => $id,
                'local_key' => $localKey,
                'http_status' => $response->status(),
                'response' => $body,
            ];
        } catch (IntegrationException $exception) {
            $this->record('POST', $this->fhirUrl($resourceType), $payload, null, ['error' => $exception->getMessage()], $localKey);

            return [
                'ok' => false,
                'local_key' => $localKey,
                'response' => ['error' => $exception->getMessage()],
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>|null  $request
     */
    protected function record(string $method, string $url, mixed $request, ?int $status, mixed $response, ?string $localKey = null): void
    {
        $this->exchanges[] = [
            'method' => $method,
            'url' => $url,
            'request' => $request,
            'status' => $status,
            'response' => $response,
            'local_key' => $localKey,
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

        $this->assertCredentialsPassGateway();

        $endpoint = $this->branch->tokenUrl();

        if (! str_contains($endpoint, 'grant_type=')) {
            $endpoint .= (str_contains($endpoint, '?') ? '&' : '?').'grant_type=client_credentials';
        }

        $payload = http_build_query([
            'client_id' => trim((string) $this->branch->client_id),
            'client_secret' => trim((string) $this->branch->client_secret),
        ], '', '&', PHP_QUERY_RFC3986);

        $response = $this->http()
            ->withBody($payload, 'application/x-www-form-urlencoded')
            ->post($endpoint);

        if (! $response->successful() || ! filled($response->json('access_token'))) {
            if ($response->json('issue') !== null || $response->json('fault') !== null || $response->status() === 429) {
                Cache::put($this->tokenBlockKey(), true, 60);
            }

            throw new IntegrationException($this->failureMessage($response, 'Token SATUSEHAT ditolak. Periksa Client ID, Client Secret, dan Token URL.'));
        }

        $token = (string) $response->json('access_token');
        $ttl = max(60, ((int) $response->json('expires_in', 3600)) - 30);
        Cache::put($this->tokenCacheKey(), $token, $ttl);

        return $token;
    }

    /**
     * Master Wilayah SATUSEHAT v2 — daftar desa/kelurahan per kecamatan (Kode Kemendagri).
     *
     * @return array<string, string> kode => "kode — nama"
     */
    public function masterWilayahSubDistricts(string $districtCode): array
    {
        $districtCode = preg_replace('/\D+/', '', $districtCode) ?? '';

        if ($districtCode === '') {
            return [];
        }

        if ($this->localSandbox()) {
            return [];
        }

        $cacheKey = 'satusehat.wilayah.subdistricts.'.$this->branch->id.'.'.$districtCode;
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $options = [];
        $page = 1;
        $cursorNext = null;

        do {
            $query = [
                'current_page' => $page,
                'district_codes' => $districtCode,
            ];

            if (filled($cursorNext)) {
                $query['next'] = $cursorNext;
            }

            $response = $this->authed()->get($this->masterdataUrl('sub-districts'), $query);
            $this->record('GET', $this->masterdataUrl('sub-districts'), $query, $response->status(), $this->responseBody($response));

            if (! $response->successful()) {
                throw new IntegrationException($this->failureMessage($response, 'Master Wilayah desa/kelurahan gagal diambil.'));
            }

            foreach ($response->json('data') ?? [] as $row) {
                if (! is_array($row) || blank($row['code'] ?? null)) {
                    continue;
                }
                $code = (string) $row['code'];
                $name = (string) ($row['name'] ?? $code);
                $options[$code] = $code.' — '.$name;
            }

            $cursorNext = $response->json('meta.cursors.next');
            $page++;
        } while (filled($cursorNext) && $page <= 100);

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);
        Cache::put($cacheKey, $options, 3600);

        return $options;
    }

    /**
     * Pastikan kode administratif ada di Master Wilayah SATUSEHAT v2.
     */
    public function assertMasterWilayahCodes(
        string $provinceCode,
        string $cityCode,
        string $districtCode,
        string $villageCode,
    ): void {
        if ($this->localSandbox()) {
            return;
        }

        $provinceCode = preg_replace('/\D+/', '', $provinceCode) ?? '';
        $cityCode = preg_replace('/\D+/', '', $cityCode) ?? '';
        $districtCode = preg_replace('/\D+/', '', $districtCode) ?? '';
        $villageCode = preg_replace('/\D+/', '', $villageCode) ?? '';

        $checks = [
            'provinsi' => ['provinces', $provinceCode, null],
            'kab/kota' => ['cities', $cityCode, ['province_codes' => $provinceCode]],
            'kecamatan' => ['districts', $districtCode, ['city_codes' => $cityCode]],
            'desa/kelurahan' => ['sub-districts', $villageCode, ['district_codes' => $districtCode]],
        ];

        foreach ($checks as $label => [$path, $code, $parent]) {
            if ($code === '') {
                throw new IntegrationException('Kode '.$label.' wajib diisi sesuai Master Wilayah SATUSEHAT (Kemendagri).');
            }

            $query = ['current_page' => 1, 'codes' => $code];
            if (is_array($parent)) {
                $query = array_merge($query, $parent);
            }

            $response = $this->authed()->get($this->masterdataUrl($path), $query);
            $this->record('GET', $this->masterdataUrl($path), $query, $response->status(), $this->responseBody($response));

            if (! $response->successful()) {
                throw new IntegrationException($this->failureMessage($response, 'Validasi Master Wilayah '.$label.' gagal.'));
            }

            $found = collect($response->json('data') ?? [])
                ->contains(fn ($row) => is_array($row) && (string) ($row['code'] ?? '') === $code);

            if (! $found) {
                throw new IntegrationException(
                    'Kode '.$label.' "'.$code.'" tidak ditemukan di Master Wilayah SATUSEHAT. Pilih ulang dari daftar Kemendagri.'
                );
            }
        }
    }

    protected function masterdataUrl(string $path): string
    {
        return $this->branch->baseUrl().'/masterdata/v2/'.ltrim($path, '/');
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

    protected function egressIp(): ?string
    {
        try {
            $ip = trim($this->http()->get('https://api.ipify.org')->body());
        } catch (\Throwable) {
            return null;
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    protected function assertCredentialsPassGateway(): void
    {
        $fields = [
            'Client ID' => (string) $this->branch->client_id,
            'Client Secret' => (string) $this->branch->client_secret,
        ];
        $patterns = [
            '/<script\b/i',
            '/union\s+select/i',
            '/information_schema/i',
            '/etc\/passwd/i',
        ];

        foreach ($fields as $label => $value) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $value) === 1) {
                    throw new IntegrationException($label.' berisi teks yang diblokir firewall SATUSEHAT. Isi ulang dari menu Kode Akses API pada environment yang sama, tanpa teks tambahan. Jika nilai aslinya memang berisi pola itu, buat ulang kode akses.');
                }
            }
        }
    }

    protected function authed(): PendingRequest
    {
        return $this->http()->withToken($this->accessToken())->asJson();
    }

    protected function http(): PendingRequest
    {
        return Http::timeout(20)
            ->acceptJson()
            ->withHeaders([
                'User-Agent' => 'KlinikDent/1.0',
                'Expect' => '',
            ])
            ->withOptions([
                'version' => 1.1,
                'proxy' => '',
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
            $ip = $this->egressIp();

            return 'Firewall SATUSEHAT menolak permintaan dari jaringan server ini (HTTP 403). IP publik server terdeteksi di luar Indonesia.'
                .($ip ? ' IP keluar: '.$ip.'.' : '')
                .' Pindahkan traffic keluar ke IP Indonesia, lalu uji lagi.';
        }

        if ($response->status() === 403) {
            return 'Akses token ditolak (HTTP 403). '.$diagnostic.' Environment di aplikasi harus sama dengan halaman Kode Akses API tempat Client ID diambil.';
        }

        return $fallback.' '.$diagnostic;
    }
}
