<?php

namespace App\Jobs;

use App\Enums\IntegrationProvider;
use App\Enums\SatuSehatStatus;
use App\Models\BranchSatuSehatSetting;
use App\Models\IntegrationLog;
use App\Models\TenantIntegration;
use App\Models\Visit;
use App\Support\Integrations\IntegrationException;
use App\Support\Integrations\IntegrationLogger;
use App\Support\Integrations\SatuSehatSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class SyncSatuSehatEncounter implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    public int $timeout = 120;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 60, 120, 300];

    public function __construct(
        public int $visitId,
        public int $logId,
    ) {}

    public function uniqueId(): string
    {
        return 'satusehat-encounter-'.$this->visitId;
    }

    public function handle(IntegrationLogger $logger): void
    {
        $visit = Visit::withoutGlobalScopes()->find($this->visitId);
        $log = IntegrationLog::withoutGlobalScopes()->find($this->logId);

        if (! $visit || ! $log) {
            return;
        }

        $tenant = TenantIntegration::forTenant((int) $visit->tenant_id, IntegrationProvider::SatuSehat);
        $branch = BranchSatuSehatSetting::withoutGlobalScopes()
            ->where('tenant_id', $visit->tenant_id)
            ->where('branch_id', $visit->branch_id)
            ->first();

        if (! $branch) {
            $logger->finish($log, false, null, 'Cabang kunjungan belum memiliki pengaturan SATUSEHAT.');
            $visit->update(['satusehat_status' => SatuSehatStatus::Failed]);

            return;
        }

        $result = (new SatuSehatSync($tenant, $branch))->run($visit);
        $partial = (bool) ($result['partial'] ?? false);
        $ok = (bool) ($result['ok'] ?? false);
        $retryable = (bool) ($result['retryable'] ?? false);

        if (! $ok && ! $partial && $retryable && $this->attempts() < $this->tries) {
            $logger->finish($log, false, $result['response'] ?? null, $result['error'] ?? null, false);
            $visit->update([
                'satusehat_status' => SatuSehatStatus::Queued,
                'satusehat_id' => $result['id'] ?? $visit->satusehat_id,
            ]);

            throw IntegrationException::temporary($result['error'] ?? 'SATUSEHAT sementara gagal.');
        }

        $logger->finish($log, $ok, $result['response'] ?? null, $result['error'] ?? null, $partial);

        $status = match (true) {
            $ok && ! $partial => SatuSehatStatus::Synced,
            $partial => SatuSehatStatus::Partial,
            default => SatuSehatStatus::Failed,
        };

        $visit->update([
            'satusehat_status' => $status,
            'satusehat_id' => $result['id'] ?? $visit->satusehat_id,
            'satusehat_synced_at' => ($ok && ! $partial) ? now() : $visit->satusehat_synced_at,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $visit = Visit::withoutGlobalScopes()->find($this->visitId);

        if ($visit && in_array($visit->satusehat_status, [SatuSehatStatus::Queued, null], true)) {
            $visit->update(['satusehat_status' => SatuSehatStatus::Failed]);
        }
    }
}
