<?php

namespace App\Support\WhatsApp;

use App\Models\WhatsAppSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WhatsAppClient
{
    public function __construct(protected WhatsAppSetting $setting) {}

    public static function forSetting(WhatsAppSetting $setting): self
    {
        return new self($setting);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, status: int, body: array<string, mixed>, message_id: ?string}
     */
    public function sendTemplate(array $payload): array
    {
        return $this->request('POST', '/api/v1/send-template', [], $payload);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function listTemplates(array $query = []): array
    {
        $result = $this->request('GET', '/api/v1/templates', array_merge([
            'status' => 'approved',
            'per_page' => 100,
        ], $query));

        return $this->collection($result['body']);
    }

    /**
     * @return array<string, mixed>
     */
    public function getTemplate(string $name, ?string $language = null, string $status = 'all'): array
    {
        $query = ['status' => $status];

        if (filled($language)) {
            $query['language'] = $this->remoteLanguage($language);
        }

        $result = $this->request('GET', '/api/v1/templates/'.rawurlencode($name), $query);
        $data = $result['body']['data'] ?? [];

        if (! is_array($data) || $data === []) {
            throw new WhatsAppException(
                (string) ($result['body']['message'] ?? 'Template tidak ditemukan.'),
                '404',
                false,
                $result['body'],
            );
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createTemplate(array $payload): array
    {
        $result = $this->request('POST', '/api/v1/templates', [], $payload);
        $data = $result['body']['data'] ?? [];

        if (! is_array($data) || $data === []) {
            throw new WhatsAppException(
                (string) ($result['body']['message'] ?? 'Gagal membuat template WhatsApp.'),
                (string) ($result['status'] ?? 'error'),
                false,
                $result['body'],
            );
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBalance(): array
    {
        return $this->payload($this->request('GET', '/api/v1/balance'));
    }

    /**
     * @return list<array<string, mixed>|string>
     */
    public function listTopupChannels(): array
    {
        $result = $this->request('GET', '/api/v1/topup/channels');
        $data = $result['body']['data'] ?? [];

        if (! is_array($data)) {
            return [];
        }

        $channels = $data['channels'] ?? $data;
        $items = [];

        if (is_array($channels) && ! array_is_list($channels) && ! isset($channels['code']) && ! isset($channels['payment_channel'])) {
            foreach ($channels as $group => $groupItems) {
                if (! is_array($groupItems)) {
                    continue;
                }

                if (! array_is_list($groupItems)) {
                    $groupItems = isset($groupItems['code']) || isset($groupItems['name'])
                        ? [$groupItems]
                        : array_values($groupItems);
                }

                foreach ($groupItems as $item) {
                    if (is_array($item)) {
                        $item['type'] = $item['type'] ?? (is_string($group) ? $group : null);
                        $items[] = $item;
                    } elseif (is_string($item) && $item !== '') {
                        $items[] = [
                            'code' => $item,
                            'name' => $item,
                            'type' => is_string($group) ? $group : null,
                        ];
                    }
                }
            }
        } elseif (is_array($channels) && array_is_list($channels)) {
            $items = $channels;
        } elseif (is_array($channels) && (isset($channels['code']) || isset($channels['payment_channel']))) {
            $items = [$channels];
        } else {
            $items = $this->collection($result['body']);
        }

        $gateway = (string) ($data['gateway'] ?? '');
        $gatewayName = (string) ($data['gateway_display_name'] ?? '');

        return array_map(function ($item) use ($gateway, $gatewayName) {
            if (! is_array($item)) {
                return $item;
            }

            $item['gateway'] = $item['gateway'] ?? $gateway;
            $item['gateway_display_name'] = $item['gateway_display_name'] ?? $gatewayName;

            return $item;
        }, $items);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createTopupLink(array $payload): array
    {
        return $this->payload($this->request('POST', '/api/v1/topup/link', [], $payload));
    }

    /**
     * @return array<string, mixed>
     */
    public function getTopupLink(string $orderId): array
    {
        return $this->payload($this->request('GET', '/api/v1/topup/link/'.rawurlencode($orderId)));
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $payload
     * @return array{ok: bool, status: int, body: array<string, mixed>, message_id: ?string}
     */
    protected function request(string $method, string $path, array $query = [], ?array $payload = null): array
    {
        $url = rtrim((string) $this->setting->api_base_url, '/').$path;

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }

        try {
            $pending = Http::withHeaders($this->headers())
                ->acceptJson()
                ->timeout(20);

            $response = $method === 'GET'
                ? $pending->get($url)
                : $pending->post($url, $payload ?? []);
        } catch (ConnectionException $exception) {
            throw new WhatsAppException(
                'Koneksi ke WhatsApp API gagal: '.$exception->getMessage(),
                'timeout',
                true,
            );
        }

        return $this->interpret($response);
    }

    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-API-KEY' => 'Bearer '.$this->setting->api_key,
        ];
    }

    /**
     * @return array{ok: bool, status: int, body: array<string, mixed>, message_id: ?string}
     */
    protected function interpret(Response $response): array
    {
        $body = $response->json() ?? ['raw' => $response->body()];
        $status = $response->status();
        $messageId = $this->messageId($body);

        if ($response->successful()) {
            return [
                'ok' => true,
                'status' => $status,
                'body' => is_array($body) ? $body : ['raw' => $body],
                'message_id' => $messageId,
            ];
        }

        $retryable = $status === 429 || $status >= 500;
        $code = (string) ($body['error_code'] ?? $body['code'] ?? $status);
        $message = (string) ($body['message'] ?? $body['error'] ?? 'WhatsApp API error '.$status);

        throw new WhatsAppException($message, $code, $retryable, $body);
    }

    /**
     * @param  mixed  $body
     */
    protected function messageId(mixed $body): ?string
    {
        if (! is_array($body)) {
            return null;
        }

        foreach (['message_id', 'id', 'data.message_id', 'data.id'] as $key) {
            $value = data_get($body, $key);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<array<string, mixed>>
     */
    protected function collection(array $body): array
    {
        $data = $body['data'] ?? [];

        if (! is_array($data)) {
            return [];
        }

        if (array_is_list($data)) {
            return $data;
        }

        if (isset($data['data']) && is_array($data['data'])) {
            return array_values($data['data']);
        }

        return $data === [] ? [] : [$data];
    }

    protected function remoteLanguage(string $language): string
    {
        $language = strtolower(trim($language));

        return match (true) {
            $language === 'id', str_starts_with($language, 'id') => 'id',
            $language === 'en', str_starts_with($language, 'en') => 'en',
            default => $language,
        };
    }

    /**
     * @param  array{ok: bool, status: int, body: array<string, mixed>, message_id: ?string}  $result
     * @return array<string, mixed>
     */
    protected function payload(array $result): array
    {
        $body = $result['body'];
        $ok = $body['status'] ?? $body['success'] ?? true;

        if ($ok === false || $ok === 0 || $ok === 'false') {
            throw new WhatsAppException(
                (string) ($body['message'] ?? 'WhatsApp API error'),
                (string) ($body['error_code'] ?? $body['code'] ?? $result['status']),
                false,
                $body,
            );
        }

        $data = $body['data'] ?? null;

        if (is_array($data) && $data !== []) {
            return $data;
        }

        $resource = $body;
        unset($resource['status'], $resource['success'], $resource['message'], $resource['meta'], $resource['raw']);

        if ($resource !== []) {
            return $resource;
        }

        throw new WhatsAppException(
            (string) ($body['message'] ?? 'Data WhatsApp tidak ditemukan.'),
            (string) ($result['status'] ?? 'error'),
            false,
            $body,
        );
    }
}
