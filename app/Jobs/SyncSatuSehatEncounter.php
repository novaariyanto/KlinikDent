<?php

namespace App\Jobs;

use App\Enums\SatuSehatStatus;
use App\Models\IntegrationLog;
use App\Models\Visit;
use App\Support\Integrations\IntegrationLogger;
use App\Support\Integrations\SatuSehatClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncSatuSehatEncounter implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $visitId,
        public int $logId,
    ) {
    }

    public function handle(IntegrationLogger $logger): void
    {
        $visit = Visit::withoutGlobalScopes()->with(['patient', 'doctor', 'branch'])->find($this->visitId);
        $log = IntegrationLog::withoutGlobalScopes()->find($this->logId);

        if (! $visit || ! $log) {
            return;
        }

        $result = SatuSehatClient::forTenant((int) $visit->tenant_id)->sendEncounter($visit);

        $logger->finish($log, $result['ok'], $result['response'] ?? null, $result['error'] ?? null);

        $visit->update([
            'satusehat_status' => $result['ok'] ? SatuSehatStatus::Synced : SatuSehatStatus::Failed,
            'satusehat_id' => $result['id'] ?? $visit->satusehat_id,
            'satusehat_synced_at' => $result['ok'] ? now() : $visit->satusehat_synced_at,
        ]);
    }
}
