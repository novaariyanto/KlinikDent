<?php

namespace App\Jobs;

use App\Enums\BpjsMembershipStatus;
use App\Models\IntegrationLog;
use App\Models\Patient;
use App\Support\Integrations\BpjsVclaimClient;
use App\Support\Integrations\IntegrationLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckBpjsMembership implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $patientId,
        public int $logId,
        public ?string $cardNumber = null,
    ) {
    }

    public function handle(IntegrationLogger $logger): void
    {
        $patient = Patient::withoutGlobalScopes()->find($this->patientId);
        $log = IntegrationLog::withoutGlobalScopes()->find($this->logId);

        if (! $patient || ! $log) {
            return;
        }

        $nik = (string) $patient->nik;
        $card = $this->cardNumber ?: $patient->bpjs_number;
        $result = BpjsVclaimClient::forTenant((int) $patient->tenant_id)->checkByNik($nik, $card);

        $logger->finish($log, $result['ok'], $result['response'] ?? null, $result['error'] ?? null);

        $patient->update([
            'bpjs_number' => $result['number'] ?? $patient->bpjs_number,
            'bpjs_status' => ($result['status'] ?? BpjsMembershipStatus::Unknown)->value,
            'bpjs_checked_at' => now(),
        ]);
    }
}
