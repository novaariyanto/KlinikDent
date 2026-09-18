<?php

namespace App\Support\Integrations;

use App\Enums\IntegrationProvider;
use App\Enums\SatuSehatStatus;
use App\Jobs\CheckBpjsMembership;
use App\Jobs\SyncSatuSehatEncounter;
use App\Models\Patient;
use App\Models\TenantIntegration;
use App\Models\Visit;

class IntegrationService
{
    public function __construct(protected IntegrationLogger $logger)
    {
    }

    public function queueSatuSehat(Visit $visit): void
    {
        $settings = TenantIntegration::forTenant((int) $visit->tenant_id, IntegrationProvider::SatuSehat);

        if (! $settings->enabled) {
            throw new IntegrationException('Modul SATUSEHAT belum diaktifkan untuk klinik ini.');
        }

        $client = new SatuSehatClient($settings);
        $payload = $client->encounterPayload($visit);
        $log = $this->logger->queued(
            IntegrationProvider::SatuSehat,
            'encounter.send',
            (int) $visit->tenant_id,
            $visit,
            $payload,
        );

        $visit->update(['satusehat_status' => SatuSehatStatus::Queued]);

        SyncSatuSehatEncounter::dispatch($visit->id, $log->id);
    }

    public function queueBpjsCheck(Patient $patient, ?string $cardNumber = null): void
    {
        $settings = TenantIntegration::forTenant((int) $patient->tenant_id, IntegrationProvider::Bpjs);

        if (! $settings->enabled) {
            throw new IntegrationException('Modul BPJS belum diaktifkan untuk klinik ini.');
        }

        $log = $this->logger->queued(
            IntegrationProvider::Bpjs,
            'membership.check',
            (int) $patient->tenant_id,
            $patient,
            [
                'nik' => $patient->nik,
                'bpjs_number' => $cardNumber ?: $patient->bpjs_number,
            ],
        );

        CheckBpjsMembership::dispatch($patient->id, $log->id, $cardNumber);
    }
}
