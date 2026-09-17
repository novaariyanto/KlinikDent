<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'subdomain' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'status' => TenantStatus::Active,
            'plan_id' => null,
        ];
    }

    public function trial(): static
    {
        return $this->state(fn () => ['status' => TenantStatus::Trial]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => TenantStatus::Suspended]);
    }
}
