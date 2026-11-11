<?php

namespace App\Support\Onboarding;

use App\Enums\OnboardingTemplate;
use App\Enums\RoleName;

final class OnboardingCatalog
{
    public const TOTAL_STEPS = 10;

    /**
     * @var array<int, string>
     */
    public const STEPS = [
        1 => 'template',
        2 => 'roles',
        3 => 'profile',
        4 => 'branch',
        5 => 'doctors',
        6 => 'staff',
        7 => 'services',
        8 => 'medicines',
        9 => 'integrations',
        10 => 'review',
    ];

    /**
     * @var list<string>
     */
    public const OPTIONAL_STEPS = [
        'doctors',
        'staff',
        'services',
        'medicines',
        'integrations',
    ];

    /**
     * @return list<string>
     */
    public static function recommendedRoles(OnboardingTemplate $template): array
    {
        return match ($template) {
            OnboardingTemplate::Small => [
                RoleName::Owner->value,
                RoleName::ClinicOps->value,
                RoleName::Dentist->value,
            ],
            OnboardingTemplate::Medium => RoleName::tenantValues(),
        };
    }

    /**
     * @return list<string>
     */
    public static function otherRoles(OnboardingTemplate $template): array
    {
        $recommended = self::recommendedRoles($template);

        return array_values(array_filter(
            RoleName::tenantValues(),
            fn (string $role) => ! in_array($role, $recommended, true)
        ));
    }

    public static function stepKey(int $number): string
    {
        return self::STEPS[$number] ?? 'template';
    }

    public static function stepNumber(string $key): int
    {
        $number = array_search($key, self::STEPS, true);

        return is_int($number) ? $number : 1;
    }

    public static function isOptional(string $step): bool
    {
        return in_array($step, self::OPTIONAL_STEPS, true);
    }

    /**
     * @return list<array{name: string, category: string, code: string, procedure: string, price: int, bhp_amount: int, doctor_fee: int, clinic_fee: int}>
     */
    public static function serviceTemplate(): array
    {
        return array_map(fn (array $item) => [
            'name' => $item['service'],
            'category' => $item['category'],
            'code' => $item['code'],
            'procedure' => $item['procedure'],
            'price' => $item['price'],
            'bhp_amount' => $item['bhp_amount'],
            'doctor_fee' => $item['doctor_fee'],
            'clinic_fee' => $item['clinic_fee'],
        ], DentalTariffCatalog::items());
    }

    /**
     * Dummy master obat dari database/master_obat.xlsx.
     *
     * @return list<array<string, mixed>>
     */
    public static function medicineTemplate(): array
    {
        return app(\App\Support\Medicine\MedicineXlsxImporter::class)->masterCatalogRows();
    }

    public static function medicineTemplateCount(): int
    {
        return count(self::medicineTemplate());
    }
}
