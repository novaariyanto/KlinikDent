<?php

namespace Database\Factories;

use App\Models\Referral;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Referral>
 */
class ReferralFactory extends Factory
{
    public function definition(): array
    {
        return [
            'visit_id' => Visit::factory(),
            'referred_to' => 'RS Gigi Rujukan',
            'reason' => 'Perawatan spesialistik',
            'notes' => null,
        ];
    }
}
