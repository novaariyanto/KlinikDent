<?php

namespace App\Support\Integrations;

use App\Enums\IntegrationLogStatus;
use App\Enums\IntegrationProvider;
use App\Models\IntegrationLog;
use Illuminate\Database\Eloquent\Model;

class IntegrationLogger
{
    /**
     * @param  array<string, mixed>|null  $request
     */
    public function queued(
        IntegrationProvider $provider,
        string $action,
        ?int $tenantId,
        ?Model $subject = null,
        ?array $request = null,
    ): IntegrationLog {
        return IntegrationLog::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId,
            'provider' => $provider,
            'action' => $action,
            'status' => IntegrationLogStatus::Queued,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'request_payload' => PayloadRedactor::redact($request),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $response
     */
    public function finish(
        IntegrationLog $log,
        bool $ok,
        ?array $response = null,
        ?string $error = null,
        bool $partial = false,
    ): IntegrationLog {
        $exchanges = [];
        if (is_array($response) && isset($response['exchanges']) && is_array($response['exchanges'])) {
            $exchanges = $response['exchanges'];
            unset($response['exchanges']);
        }

        $status = match (true) {
            $ok && ! $partial => IntegrationLogStatus::Success,
            $partial => IntegrationLogStatus::Partial,
            default => IntegrationLogStatus::Failed,
        };

        $log->update([
            'status' => $status,
            'response_payload' => PayloadRedactor::redact($response),
            'error_message' => $error,
        ]);

        if ($exchanges !== []) {
            $this->storeCalls($log, $exchanges);
        }

        return $log->fresh();
    }

    /**
     * @param  list<array{method?: string, url?: string, request?: mixed, status?: int|null, response?: mixed}>  $exchanges
     */
    public function storeCalls(IntegrationLog $log, array $exchanges): void
    {
        $request = $log->request_payload ?? [];
        $response = $log->response_payload ?? [];

        $request['calls'] = array_map(fn (array $call) => [
            'method' => $call['method'] ?? null,
            'url' => $call['url'] ?? null,
            'body' => $call['request'] ?? null,
        ], $exchanges);

        $response['calls'] = array_map(fn (array $call) => [
            'method' => $call['method'] ?? null,
            'url' => $call['url'] ?? null,
            'status' => $call['status'] ?? null,
            'body' => $call['response'] ?? null,
        ], $exchanges);

        $log->update([
            'request_payload' => PayloadRedactor::redact($request),
            'response_payload' => PayloadRedactor::redact($response),
        ]);
    }
}
