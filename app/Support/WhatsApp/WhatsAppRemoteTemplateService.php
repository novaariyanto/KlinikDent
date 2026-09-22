<?php

namespace App\Support\WhatsApp;

use App\Models\WhatsAppSetting;
use App\Models\WhatsAppTenantTemplate;
use Illuminate\Support\Facades\Cache;

class WhatsAppRemoteTemplateService
{
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
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function listApproved(int $tenantId, array $query = []): array
    {
        $cacheKey = 'whatsapp.remote-templates.'.$tenantId;

        if ($query === []) {
            return Cache::remember($cacheKey, 60, fn () => $this->clientForTenant($tenantId)->listTemplates());
        }

        return $this->clientForTenant($tenantId)->listTemplates($query);
    }

    public function forgetListCache(int $tenantId): void
    {
        Cache::forget('whatsapp.remote-templates.'.$tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(int $tenantId, string $name, ?string $language = null, string $status = 'all'): array
    {
        $name = trim($name);
        $client = $this->clientForTenant($tenantId);
        $remote = $this->fetchByName($client, $name, $language, $status);

        if ($remote === null) {
            $match = $this->matchFromList($tenantId, $name);
            $actual = trim((string) ($match['name_template'] ?? $match['name'] ?? ''));

            if ($actual !== '' && strcasecmp($actual, $name) !== 0) {
                $remote = $this->fetchByName($client, $actual, null, $status) ?? $match;
            } else {
                $remote = $match;
            }
        }

        if (! is_array($remote) || $remote === []) {
            throw new WhatsAppException(
                "Template WhatsCRM '{$name}' tidak ditemukan. Pilih template approved dari daftar, atau ajukan template baru ke Meta. Nama yang dicari adalah name_template WhatsCRM, bukan kode event KlinikDent.",
                '404',
            );
        }

        return $this->normalize($remote);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchByName(WhatsAppClient $client, string $name, ?string $language, string $status): ?array
    {
        foreach ($this->languageAttempts($language) as $attempt) {
            try {
                return $client->getTemplate($name, $attempt, $status);
            } catch (WhatsAppException $exception) {
                if (! $this->isNotFound($exception)) {
                    throw $exception;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string|null>
     */
    protected function languageAttempts(?string $language): array
    {
        $language = strtolower(trim((string) $language));
        $attempts = [null];

        if ($language !== '') {
            array_unshift($attempts, $language);
        }

        if (str_starts_with($language, 'id')) {
            $attempts[] = 'id';
        } elseif (str_starts_with($language, 'en')) {
            $attempts[] = 'en';
        }

        return array_values(array_unique($attempts, SORT_REGULAR));
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function matchFromList(int $tenantId, string $name): ?array
    {
        $needle = mb_strtolower($name);
        $lists = [];

        try {
            $lists[] = $this->listApproved($tenantId);
        } catch (WhatsAppException) {
        }

        try {
            $lists[] = $this->clientForTenant($tenantId)->listTemplates([
                'search' => $name,
                'status' => 'all',
            ]);
        } catch (WhatsAppException) {
        }

        $partial = null;

        foreach ($lists as $list) {
            foreach ($list as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $candidates = [
                    (string) ($item['name_template'] ?? ''),
                    (string) ($item['name'] ?? ''),
                    (string) ($item['display_name'] ?? ''),
                ];

                foreach ($candidates as $candidate) {
                    $haystack = mb_strtolower($candidate);

                    if ($haystack === $needle && $needle !== '') {
                        return $item;
                    }

                    if ($partial === null && $haystack !== '' && str_contains($haystack, $needle)) {
                        $partial = $item;
                    }
                }
            }
        }

        return $partial;
    }

    protected function isNotFound(WhatsAppException $exception): bool
    {
        $code = (string) $exception->errorCode;
        $message = mb_strtolower($exception->getMessage());

        return in_array($code, ['404', '422'], true)
            || str_contains($message, 'tidak ditemukan')
            || str_contains($message, 'not found');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(int $tenantId, array $payload): array
    {
        $created = $this->clientForTenant($tenantId)->createTemplate($payload);
        $this->forgetListCache($tenantId);

        return $this->normalize($created);
    }

    /**
     * @param  array<string, mixed>  $remote
     */
    public function applyToTenantTemplate(WhatsAppTenantTemplate $template, array $remote, bool $save = true): WhatsAppTenantTemplate
    {
        $normalized = $this->normalize($remote);
        $preview = (string) $normalized['preview'];

        $template->whatsapp_template_name = (string) ($normalized['name_template'] ?: $template->whatsapp_template_name);
        $template->language_code = (string) ($normalized['language'] ?: $template->language_code ?: 'id');
        $template->preview_body = $preview;
        $template->parameter_map = WhatsAppParameterParser::mergeMapping($preview, $template->parameter_map ?? []);
        $template->remote_status = $normalized['status'];
        $template->remote_synced_at = now();
        $template->remote_meta = $normalized['meta'];

        if ($normalized['status'] !== 'approved') {
            $template->is_enabled = false;
        }

        if ($save) {
            $template->save();
        }

        return $template;
    }

    /**
     * @param  array<string, mixed>  $remote
     * @return array<string, mixed>
     */
    public function normalize(array $remote): array
    {
        $parsed = WhatsAppParameterParser::fromRemote(
            (string) ($remote['body'] ?? $remote['body_text'] ?? $remote['preview'] ?? ''),
            $remote['placeholders'] ?? [],
        );

        $name = (string) ($remote['name_template'] ?? $remote['name'] ?? '');
        $status = strtolower((string) ($remote['status'] ?? ''));
        $language = strtolower((string) ($remote['language'] ?? 'id'));

        if (str_starts_with($language, 'en')) {
            $language = 'en';
        } elseif (str_starts_with($language, 'id')) {
            $language = 'id';
        }

        return [
            'id' => $remote['id'] ?? null,
            'name_template' => $name,
            'display_name' => (string) ($remote['display_name'] ?? $remote['name'] ?? $name),
            'language' => $language !== '' ? $language : 'id',
            'category' => (string) ($remote['category'] ?? ''),
            'status' => $status !== '' ? $status : null,
            'preview' => $parsed['preview'],
            'positions' => $parsed['positions'],
            'placeholders' => $remote['placeholders'] ?? [],
            'header_info' => $remote['header_info'] ?? null,
            'meta' => [
                'id' => $remote['id'] ?? null,
                'display_name' => $remote['display_name'] ?? $remote['name'] ?? $name,
                'placeholders' => $remote['placeholders'] ?? [],
                'header_info' => $remote['header_info'] ?? null,
                'category' => $remote['category'] ?? null,
            ],
        ];
    }
}
