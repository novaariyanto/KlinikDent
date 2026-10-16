<?php

namespace Tests\Unit;

use App\Enums\SharingFeeCalcType;
use App\Enums\SharingFeeMaterialDeductionType;
use App\Models\SharingFeeRule;
use App\Support\Finance\SharingFee\SharingFeeCalculator;
use App\Support\Finance\SharingFee\SharingFeeRuleResolver;
use PHPUnit\Framework\TestCase;

class SharingFeeCalculatorTest extends TestCase
{
    public function test_percentage_and_fixed_fee_formulas(): void
    {
        $calc = new SharingFeeCalculator;

        $percentRule = new SharingFeeRule([
            'calc_type' => SharingFeeCalcType::Percentage,
            'rate' => '40',
            'material_deduction_type' => SharingFeeMaterialDeductionType::None,
            'material_deduction_value' => '0',
        ]);

        $percent = $calc->calculate($percentRule, '250000.00', 1);
        $this->assertSame('250000.00', $percent['gross_base']);
        $this->assertSame('0.00', $percent['material_deduction']);
        $this->assertSame('250000.00', $percent['fee_base']);
        $this->assertSame('100000.00', $percent['fee_amount']);

        $fixedRule = new SharingFeeRule([
            'calc_type' => SharingFeeCalcType::Fixed,
            'rate' => '75000',
            'material_deduction_type' => SharingFeeMaterialDeductionType::Percent,
            'material_deduction_value' => '10',
        ]);

        $fixed = $calc->calculate($fixedRule, '250000.00', 2);
        // Potongan dihitung, tapi fee nominal tetap = tarif × qty (tidak dikurangi potongan).
        $this->assertSame('25000.00', $fixed['material_deduction']);
        $this->assertSame('225000.00', $fixed['fee_base']);
        $this->assertSame('150000.00', $fixed['fee_amount']);
    }

    public function test_material_deduction_percent_and_fixed(): void
    {
        $calc = new SharingFeeCalculator;

        $percentDeduction = new SharingFeeRule([
            'calc_type' => SharingFeeCalcType::Percentage,
            'rate' => '50',
            'material_deduction_type' => SharingFeeMaterialDeductionType::Percent,
            'material_deduction_value' => '10',
        ]);

        $result = $calc->calculate($percentDeduction, '100000.00', 1);
        $this->assertSame('10000.00', $result['material_deduction']);
        $this->assertSame('90000.00', $result['fee_base']);
        $this->assertSame('45000.00', $result['fee_amount']);

        $fixedDeduction = new SharingFeeRule([
            'calc_type' => SharingFeeCalcType::Percentage,
            'rate' => '100',
            'material_deduction_type' => SharingFeeMaterialDeductionType::Fixed,
            'material_deduction_value' => '15000',
        ]);

        $result2 = $calc->calculate($fixedDeduction, '100000.00', 2);
        $this->assertSame('30000.00', $result2['material_deduction']);
        $this->assertSame('70000.00', $result2['fee_base']);
        $this->assertSame('70000.00', $result2['fee_amount']);
    }

    public function test_decimal_rounding_uses_scale_two(): void
    {
        $calc = new SharingFeeCalculator;

        $rule = new SharingFeeRule([
            'calc_type' => SharingFeeCalcType::Percentage,
            'rate' => '33.3333',
            'material_deduction_type' => SharingFeeMaterialDeductionType::None,
            'material_deduction_value' => '0',
        ]);

        $result = $calc->calculate($rule, '100.00', 1);
        $this->assertSame('33.33', $result['fee_amount']);
    }

    public function test_specificity_rank_order_matches_business_rules(): void
    {
        $resolver = new SharingFeeRuleResolver;

        $this->assertSame(8, $resolver->specificityRank(new SharingFeeRule([
            'doctor_user_id' => 1, 'branch_id' => 2, 'procedure_id' => 3,
        ])));
        $this->assertSame(7, $resolver->specificityRank(new SharingFeeRule([
            'doctor_user_id' => 1, 'branch_id' => null, 'procedure_id' => 3,
        ])));
        $this->assertSame(6, $resolver->specificityRank(new SharingFeeRule([
            'doctor_user_id' => 1, 'branch_id' => 2, 'procedure_id' => null,
        ])));
        $this->assertSame(5, $resolver->specificityRank(new SharingFeeRule([
            'doctor_user_id' => null, 'branch_id' => 2, 'procedure_id' => 3,
        ])));
        $this->assertSame(4, $resolver->specificityRank(new SharingFeeRule([
            'doctor_user_id' => 1, 'branch_id' => null, 'procedure_id' => null,
        ])));
        $this->assertSame(3, $resolver->specificityRank(new SharingFeeRule([
            'doctor_user_id' => null, 'branch_id' => null, 'procedure_id' => 3,
        ])));
        $this->assertSame(2, $resolver->specificityRank(new SharingFeeRule([
            'doctor_user_id' => null, 'branch_id' => 2, 'procedure_id' => null,
        ])));
        $this->assertSame(1, $resolver->specificityRank(new SharingFeeRule([
            'doctor_user_id' => null, 'branch_id' => null, 'procedure_id' => null,
        ])));

        // Cabang+tindakan (rank 5) mengalahkan dokter saja (rank 4).
        $this->assertGreaterThan(
            $resolver->specificityRank(new SharingFeeRule(['doctor_user_id' => 1])),
            $resolver->specificityRank(new SharingFeeRule(['branch_id' => 2, 'procedure_id' => 3])),
        );
    }

    public function test_matches_null_means_all_scope(): void
    {
        $resolver = new SharingFeeRuleResolver;
        $general = new SharingFeeRule([
            'doctor_user_id' => null,
            'branch_id' => null,
            'procedure_id' => null,
        ]);

        $this->assertTrue($resolver->matches($general, 9, 8, 7));

        $doctorOnly = new SharingFeeRule([
            'doctor_user_id' => 9,
            'branch_id' => null,
            'procedure_id' => null,
        ]);
        $this->assertTrue($resolver->matches($doctorOnly, 9, 1, 2));
        $this->assertFalse($resolver->matches($doctorOnly, 10, 1, 2));
    }
}
