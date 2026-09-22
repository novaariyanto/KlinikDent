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
                RoleName::Dentist->value,
                RoleName::Registration->value,
                RoleName::Cashier->value,
                RoleName::Pharmacy->value,
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
     * @return list<array{name: string, category: string, code: string, procedure: string, price: int}>
     */
    public static function serviceTemplate(): array
    {
        return [
            ['name' => 'Konsultasi', 'category' => 'konsultasi', 'code' => 'KSL-01', 'procedure' => 'Konsultasi', 'price' => 150000],
            ['name' => 'Pemeriksaan Gigi', 'category' => 'konsultasi', 'code' => 'PMK-01', 'procedure' => 'Pemeriksaan Gigi', 'price' => 200000],
            ['name' => 'Scaling', 'category' => 'preventif', 'code' => 'SCL-01', 'procedure' => 'Scaling', 'price' => 350000],
            ['name' => 'Tambal Gigi', 'category' => 'restorasi', 'code' => 'TBL-01', 'procedure' => 'Tambal Gigi', 'price' => 400000],
            ['name' => 'Pencabutan Gigi', 'category' => 'bedah', 'code' => 'CBT-01', 'procedure' => 'Pencabutan Gigi', 'price' => 500000],
            ['name' => 'Kontrol', 'category' => 'konsultasi', 'code' => 'KTR-01', 'procedure' => 'Kontrol', 'price' => 100000],
        ];
    }

    /**
     * @return list<array{name: string, unit: string, category: string, base_price: int}>
     */
    public static function medicineTemplate(): array
    {
        return [
            ['name' => 'Paracetamol 500mg', 'unit' => 'tablet', 'category' => 'analgesik', 'base_price' => 1500],
            ['name' => 'Amoxicillin 500mg', 'unit' => 'kapsul', 'category' => 'antibiotik', 'base_price' => 2500],
            ['name' => 'Chlorhexidine Mouthwash', 'unit' => 'botol', 'category' => 'antiseptik', 'base_price' => 35000],
        ];
    }
}
