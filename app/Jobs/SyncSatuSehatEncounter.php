<?php

namespace App\Jobs;

use App\Enums\IntegrationProvider;
use App\Enums\SatuSehatStatus;
use App\Models\BranchSatuSehatSetting;
use App\Models\IntegrationLog;
use App\Models\TenantIntegration;
use App\Models\Visit;
use App\Support\Integrations\IntegrationLogger;
use App\Support\Integrations\SatuSehatSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncSatuSehatEncounter implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $visitId,
        public int $logId,
    ) {}

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

        $logger->finish($log, $result['ok'], $result['response'] ?? null, $result['error'] ?? null);

        $visit->update([
            'satusehat_status' => $result['ok'] ? SatuSehatStatus::Synced : SatuSehatStatus::Failed,
            'satusehat_id' => $result['id'] ?? $visit->satusehat_id,
            'satusehat_synced_at' => $result['ok'] ? now() : $visit->satusehat_synced_at,
        ]);
    }
}
