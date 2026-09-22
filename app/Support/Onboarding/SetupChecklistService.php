<?php

namespace App\Support\Onboarding;

use App\Enums\IntegrationProvider;
use App\Enums\RoleName;
use App\Models\Doctor;
use App\Models\Service;
use App\Models\Tariff;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\User;
use App\Support\ClinicSettings;

class SetupChecklistService
{
    /**
     * @return array{percent: int, items: list<array{key: string, label: string, done: bool, href: string, optional: bool}>, complete: bool}|null
     */
    public function forOwner(User $user): ?array
    {
        if (! $user->hasRole(RoleName::Owner) || ! $user->tenant_id) {
            return null;
        }

        $tenant = $user->tenant;

        if (! $tenant instanceof Tenant) {
            return null;
        }

        $settings = ClinicSettings::current((int) $tenant->id);
        $satusehat = TenantIntegration::forTenant((int) $tenant->id, IntegrationProvider::SatuSehat);
        $bpjs = TenantIntegration::forTenant((int) $tenant->id, IntegrationProvider::Bpjs);
        $hasServices = Service::query()->where('tenant_id', $tenant->id)->exists();
        $hasTariffs = Tariff::query()->where('tenant_id', $tenant->id)->exists();

        $items = [
            [
                'key' => 'profile',
                'label' => 'Profil klinik',
                'done' => filled($settings['clinic_name'] ?? null) && filled($settings['address'] ?? null),
                'href' => route('settings.clinic'),
                'optional' => false,
            ],
            [
                'key' => 'branch',
                'label' => 'Cabang',
                'done' => $tenant->branches()->exists(),
                'href' => route('branches.index'),
                'optional' => false,
            ],
            [
                'key' => 'doctors',
                'label' => 'Dokter',
                'done' => Doctor::query()->where('tenant_id', $tenant->id)->exists(),
                'href' => route('doctors.index'),
                'optional' => false,
            ],
            [
                'key' => 'staff',
                'label' => 'User / staf',
                'done' => User::query()->where('tenant_id', $tenant->id)->whereKeyNot($user->id)->exists(),
                'href' => route('users.index'),
                'optional' => false,
            ],
            [
                'key' => 'services',
                'label' => 'Layanan & tarif',
                'done' => $hasServices && $hasTariffs,
                'href' => route('management.services.index'),
                'optional' => false,
            ],
            [
                'key' => 'satusehat',
                'label' => 'Integrasi SATUSEHAT',
                'done' => $satusehat->isConfigured(),
                'href' => route('integrations.satusehat'),
                'optional' => true,
            ],
            [
                'key' => 'bpjs',
                'label' => 'Integrasi BPJS',
                'done' => $bpjs->isConfigured(),
                'href' => route('integrations.bpjs'),
                'optional' => true,
            ],
        ];

        $required = array_values(array_filter($items, fn (array $item) => ! $item['optional']));
        $requiredDone = count(array_filter($required, fn (array $item) => $item['done']));
        $requiredTotal = count($required);

        if ($requiredDone === $requiredTotal) {
            return null;
        }

        return [
            'percent' => $requiredTotal ? (int) round(($requiredDone / $requiredTotal) * 100) : 100,
            'items' => $items,
            'complete' => false,
        ];
    }
}
