<?php

namespace App\Support\WhatsApp;

use App\Enums\WhatsAppMessageStatus;
use App\Jobs\SendWhatsAppTemplateJob;
use App\Models\WhatsAppMessageLog;
use App\Models\WhatsAppSetting;
use App\Models\WhatsAppTenantTemplate;
use App\Support\Integrations\PayloadRedactor;
use Illuminate\Support\Facades\Log;

class WhatsAppTemplateService
{
    public function __construct(
        protected WhatsAppParameterResolver $resolver,
        protected WhatsAppProvisioningService $provisioning,
    ) {}

    /**
     * @return list<string>
     */
    public function resolveParameters(WhatsAppTenantTemplate $template, WhatsAppMessageContext $context): array
    {
        return $this->resolver->resolveAll($template->resolvedParameterMap(), $context);
    }

    /**
     * @param  list<array<string, mixed>>  $mapping
     */
    public function validateParameters(array $mapping): void
    {
        $missing = WhatsAppParameterParser::missing($mapping);

        if ($missing !== []) {
            throw new WhatsAppException(
                'Mapping parameter belum lengkap: '.implode(', ', $missing),
                'invalid_parameters',
            );
        }
    }

    /**
     * @param  list<string>  $resolved
     * @return array<string, mixed>
     */
    public function buildPayload(
        WhatsAppTenantTemplate $template,
        string $phone,
        string $toName,
        array $resolved,
    ): array {
        return [
            'phone_number' => $phone,
            'to_name' => $toName,
            'name_template' => $template->whatsapp_template_name,
            'language_code' => $template->language_code,
            'body_params' => array_values($resolved),
        ];
    }

    public function sendLog(WhatsAppMessageLog $log): WhatsAppMessageLog
    {
        $log = WhatsAppMessageLog::query()->withoutGlobalScopes()->find($log->id) ?? $log;

        if ($log->status->isSuccess()) {
            return $log;
        }

        $setting = WhatsAppSetting::query()->withoutGlobalScopes()->where('tenant_id', $log->tenant_id)->first();

        if (! $setting?->isReady()) {
            return $this->fail($log, 'missing_credentials', 'WhatsApp belum terhubung.');
        }

        $payload = $log->request_payload ?? [];

        if ($payload === [] || isset($payload['body_params']) === false) {
            return $this->fail($log, 'invalid_payload', 'Payload pengiriman tidak lengkap.');
        }

        $template = WhatsAppTenantTemplate::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $log->tenant_id)
            ->where('event_key', $log->event_key)
            ->first();

        if ($template && ! $template->isRemoteApproved()) {
            return $this->fail($log, 'template_not_approved', 'Template WhatsCRM belum approved.');
        }

        foreach ($payload['body_params'] ?? [] as $value) {
            if (is_string($value) && preg_match('/^\{\{\d+\}\}$/', $value)) {
                return $this->fail($log, 'literal_placeholder', 'Parameter masih berupa placeholder.');
            }
        }

        $log->update(['status' => WhatsAppMessageStatus::Processing]);

        try {
            $result = WhatsAppClient::forSetting($setting)->sendTemplate($payload);
        } catch (WhatsAppException $exception) {
            $this->fail($log, $exception->errorCode, $exception->getMessage(), $exception->response);

            if ($exception->retryable) {
                throw $exception;
            }

            return $log->fresh();
        }

        $log->update([
            'status' => WhatsAppMessageStatus::Sent,
            'provider_message_id' => $result['message_id'],
            'response_payload' => PayloadRedactor::redact($result['body']),
            'error_code' => null,
            'error_message' => null,
            'failed_at' => null,
            'sent_at' => now(),
        ]);

        return $log->fresh();
    }

    /**
     * @param  array<int|string, mixed>  $bodyParams
     */
    public function sendTest(
        WhatsAppTenantTemplate $template,
        string $phone,
        string $toName,
        array $bodyParams,
    ): WhatsAppMessageLog {
        $normalized = WhatsAppPhone::normalize($phone);

        if (! $normalized) {
            throw new WhatsAppException('Nomor WhatsApp tidak valid.', 'invalid_phone');
        }

        if (! $template->isRemoteApproved()) {
            throw new WhatsAppException('Template WhatsCRM belum approved.', 'template_not_approved');
        }

        $setting = WhatsAppSetting::query()->withoutGlobalScopes()->where('tenant_id', $template->tenant_id)->first();

        if (! $setting?->isReady()) {
            throw new WhatsAppException('WhatsApp belum terhubung. Isi API key di pengaturan.', 'missing_credentials');
        }

        $resolved = [];

        foreach (WhatsAppParameterParser::positions((string) $template->preview_body) as $position) {
            $value = trim((string) ($bodyParams[$position] ?? $bodyParams[(string) $position] ?? ''));

            if ($value === '' || preg_match('/^\{\{\d+\}\}$/', $value)) {
                throw new WhatsAppException('Semua parameter tes wajib diisi dengan nilai nyata.', 'invalid_parameters');
            }

            $resolved[] = $value;
        }

        $payload = $this->buildPayload($template, $normalized, $toName !== '' ? $toName : 'Tes', $resolved);

        $log = WhatsAppMessageLog::query()->create([
            'tenant_id' => $template->tenant_id,
            'branch_id' => auth()->user()?->branch_id,
            'event_key' => $template->event_key,
            'category' => $template->event_key->category()->value,
            'template_name' => $template->whatsapp_template_name,
            'phone_number' => $normalized,
            'to_name' => $toName !== '' ? $toName : 'Tes',
            'body_params' => $resolved,
            'status' => WhatsAppMessageStatus::Queued,
            'idempotency_key' => 'test:'.$template->id.':'.uniqid('', true),
            'related_type' => $template->getMorphClass(),
            'related_id' => $template->id,
            'scheduled_type' => 'test',
            'request_payload' => $payload,
        ]);

        return $this->sendLog($log);
    }

    public function dispatch(WhatsAppMessageLog $log): void
    {
        SendWhatsAppTemplateJob::dispatch($log->id);
    }

    /**
     * @param  mixed  $response
     */
    protected function fail(WhatsAppMessageLog $log, ?string $code, string $message, mixed $response = null): WhatsAppMessageLog
    {
        $log->update([
            'status' => WhatsAppMessageStatus::Failed,
            'error_code' => $code,
            'error_message' => $message,
            'failed_at' => now(),
            'response_payload' => is_array($response) ? PayloadRedactor::redact($response) : $log->response_payload,
        ]);

        Log::warning('WhatsApp send failed', [
            'log_id' => $log->id,
            'tenant_id' => $log->tenant_id,
            'event' => $log->event_key?->value,
            'error_code' => $code,
        ]);

        return $log->fresh();
    }
}
