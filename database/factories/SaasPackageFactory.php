<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Models\SaasPackage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SaasPackage>
 */
class SaasPackageFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::headline($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'price' => '250000.00',
            'interval' => BillingInterval::Monthly,
            'trial_days' => 14,
            'max_branches' => 3,
            'max_users' => 10,
            'is_active' => true,
        ];
    }

    public function trial(): static
    {
        return $this->state(fn () => [
            'name' => 'Trial',
            'slug' => 'trial',
            'price' => '0.00',
            'trial_days' => 14,
        ]);
    }
}
