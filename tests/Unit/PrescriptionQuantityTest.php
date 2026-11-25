<?php

namespace Tests\Unit;

use App\Support\Clinical\PrescriptionQuantity;
use PHPUnit\Framework\TestCase;

class PrescriptionQuantityTest extends TestCase
{
    public function test_quantity_is_signa_sum_times_jho_not_dose_times_signa(): void
    {
        $result = PrescriptionQuantity::assess([
            'kind' => 'ready',
            'unit' => 'tablet',
            'dosage' => '500 mg',
            'signa_p' => '1',
            'signa_s' => '0',
            'signa_sr' => '1',
            'signa_m' => '0',
            'jho' => 3,
            'quantity' => 6,
        ]);

        $this->assertSame('calculated', $result['mode']);
        $this->assertSame(6, $result['quantity']);
        $this->assertSame(3, $result['days']);
    }

    public function test_zero_signa_returns_quantity_one(): void
    {
        $result = PrescriptionQuantity::assess([
            'kind' => 'ready',
            'unit' => 'tablet',
            'signa_p' => '0',
            'signa_s' => '0',
            'signa_sr' => '0',
            'signa_m' => '0',
            'jho' => 3,
        ]);

        $this->assertSame('calculated', $result['mode']);
        $this->assertSame(1, $result['quantity']);
    }

    public function test_liquid_signa_is_summed_in_millilitres(): void
    {
        $result = PrescriptionQuantity::assess([
            'kind' => 'ready',
            'unit' => 'sirup',
            'dosage' => '5 ml',
            'signa_p' => '5',
            'signa_s' => '0',
            'signa_sr' => '5',
            'signa_m' => '0',
            'jho' => 3,
            'quantity' => 30,
        ]);

        $this->assertSame('calculated', $result['mode']);
        $this->assertSame(30, $result['quantity']);
        $this->assertSame('ml', $result['unit']);
    }

    public function test_frequency_pair_is_expanded_to_daily_units(): void
    {
        $result = PrescriptionQuantity::assess([
            'kind' => 'ready',
            'unit' => 'tablet',
            'frequency' => '3×2',
            'jho' => 5,
            'quantity' => 30,
        ]);

        $this->assertSame('calculated', $result['mode']);
        $this->assertSame(30, $result['quantity']);
        $this->assertSame(3, $result['administrations']);
    }

    public function test_prn_is_manual(): void
    {
        $result = PrescriptionQuantity::assess([
            'kind' => 'ready',
            'unit' => 'tablet',
            'frequency' => 'PRN',
            'signa_p' => '1',
            'jho' => 3,
        ]);

        $this->assertSame('manual', $result['mode']);
        $this->assertNull($result['quantity']);
    }

    public function test_until_finished_without_jho_is_manual(): void
    {
        $result = PrescriptionQuantity::assess([
            'kind' => 'ready',
            'unit' => 'tablet',
            'frequency' => '1×1',
            'waktu' => 'sampai habis',
            'signa_p' => '1',
        ]);

        $this->assertSame('manual', $result['mode']);
    }

    public function test_signa_line_uses_dash_format(): void
    {
        $this->assertSame('1-0-1-0', PrescriptionQuantity::signaLine([
            'signa_p' => '1',
            'signa_s' => '',
            'signa_sr' => '1',
            'signa_m' => '',
        ]));

        $this->assertSame('', PrescriptionQuantity::signaLine([
            'signa_p' => '',
            'signa_s' => '',
            'signa_sr' => '',
            'signa_m' => '',
        ]));
    }

    public function test_ingredient_quantity_uses_dose_times_packs_divided_by_strength(): void
    {
        $input = [
            'kind' => 'racikan',
            'medication_type' => 'EP',
            'unit' => 'bungkus',
            'signa_p' => '1',
            'signa_s' => '0',
            'signa_sr' => '1',
            'signa_m' => '0',
            'jho' => 5,
            'quantity' => 10,
        ];

        $ingredients = [[
            'name' => 'Amoxicillin',
            'role' => 'active',
            'quantity' => 5,
            'dose_amount' => 250,
            'strength_value' => 500,
        ]];

        $this->assertNull(PrescriptionQuantity::ingredientMessage($input, $ingredients));

        $ingredients[0]['quantity'] = 3;
        $this->assertNotNull(PrescriptionQuantity::ingredientMessage($input, $ingredients));
    }
}
