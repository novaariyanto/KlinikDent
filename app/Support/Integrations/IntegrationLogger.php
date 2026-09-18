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
    public function finish(IntegrationLog $log, bool $ok, ?array $response = null, ?string $error = null): IntegrationLog
    {
        $log->update([
            'status' => $ok ? IntegrationLogStatus::Success : IntegrationLogStatus::Failed,
            'response_payload' => PayloadRedactor::redact($response),
            'error_message' => $error,
        ]);

        return $log->fresh();
    }
}
