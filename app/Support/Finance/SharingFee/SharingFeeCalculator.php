<?php

namespace App\Support\Finance\SharingFee;

use App\Enums\SharingFeeCalcType;
use App\Enums\SharingFeeMaterialDeductionType;
use App\Models\SharingFeeRule;

class SharingFeeCalculator
{
    /**
     * @return array{
     *     gross_base: string,
     *     material_deduction: string,
     *     fee_base: string,
     *     fee_amount: string,
     *     calc_type: string,
     *     rate: string
     * }
     */
    public function calculate(SharingFeeRule $rule, string $grossBase, int $quantity): array
    {
        $grossBase = $this->normalize($grossBase);
        $quantity = max(1, $quantity);

        $deduction = $this->materialDeduction($rule, $grossBase, $quantity);
        $feeBase = bcsub($grossBase, $deduction, 2);

        if (bccomp($feeBase, '0', 2) < 0) {
            $feeBase = '0.00';
        }

        $calcType = $rule->calc_type instanceof SharingFeeCalcType
            ? $rule->calc_type
            : SharingFeeCalcType::from((string) $rule->calc_type);

        $rate = $this->normalize((string) $rule->rate, 4);

        $feeAmount = match ($calcType) {
            SharingFeeCalcType::Percentage => bcdiv(bcmul($feeBase, $rate, 4), '100', 2),
            SharingFeeCalcType::Fixed => bcmul($rate, (string) $quantity, 2),
        };

        return [
            'gross_base' => $grossBase,
            'material_deduction' => $deduction,
            'fee_base' => $feeBase,
            'fee_amount' => $feeAmount,
            'calc_type' => $calcType->value,
            'rate' => $rate,
        ];
    }

    public function materialDeduction(SharingFeeRule $rule, string $grossBase, int $quantity): string
    {
        $type = $rule->material_deduction_type instanceof SharingFeeMaterialDeductionType
            ? $rule->material_deduction_type
            : SharingFeeMaterialDeductionType::from((string) ($rule->material_deduction_type ?? 'none'));

        $value = $this->normalize((string) ($rule->material_deduction_value ?? '0'), 4);

        return match ($type) {
            SharingFeeMaterialDeductionType::None => '0.00',
            SharingFeeMaterialDeductionType::Percent => bcdiv(bcmul($grossBase, $value, 4), '100', 2),
            SharingFeeMaterialDeductionType::Fixed => bcmul($value, (string) max(1, $quantity), 2),
        };
    }

    public function normalize(string $amount, int $scale = 2): string
    {
        $amount = trim($amount);

        if ($amount === '' || ! is_numeric($amount)) {
            return number_format(0, $scale, '.', '');
        }

        return bcadd($amount, '0', $scale);
    }
}
