<?php

namespace App\Support\Finance\SharingFee;

use App\Enums\SharingFeeCalcType;
use App\Models\InvoiceItem;
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
     *     rate: string,
     *     base_source: string
     * }
     */
    public function calculateFromInvoiceItem(SharingFeeRule $rule, InvoiceItem $item): array
    {
        $quantity = max(1, (int) $item->quantity);

        if ($item->usesDoctorFeeSharing()) {
            $calc = $this->calculate($rule, $item->sharingDoctorFeeTotal(), $quantity);
            $calc['material_deduction'] = $item->sharingBhpTotal();
            $calc['base_source'] = 'doctor_fee';

            return $calc;
        }

        $legacyRule = $rule;
        $calcType = $rule->calc_type instanceof SharingFeeCalcType
            ? $rule->calc_type
            : SharingFeeCalcType::from((string) $rule->calc_type);

        if ($calcType === SharingFeeCalcType::DoctorFee) {
            $legacyRule = new SharingFeeRule([
                'calc_type' => SharingFeeCalcType::Percentage,
                'rate' => '40',
            ]);
        }

        $calc = $this->calculate($legacyRule, $item->sharingFeeBase(), $quantity);
        $calc['base_source'] = 'subtotal';
        $calc['legacy_fallback'] = $calcType === SharingFeeCalcType::DoctorFee;

        return $calc;
    }

    /**
     * @return array{
     *     gross_base: string,
     *     material_deduction: string,
     *     fee_base: string,
     *     fee_amount: string,
     *     calc_type: string,
     *     rate: string,
     *     base_source: string
     * }
     */
    public function calculate(SharingFeeRule $rule, string $grossBase, int $quantity): array
    {
        $grossBase = $this->normalize($grossBase);
        $quantity = max(1, $quantity);
        $feeBase = $grossBase;

        $calcType = $rule->calc_type instanceof SharingFeeCalcType
            ? $rule->calc_type
            : SharingFeeCalcType::from((string) $rule->calc_type);

        $rate = $calcType === SharingFeeCalcType::DoctorFee
            ? '100.0000'
            : $this->normalize((string) $rule->rate, 4);

        $feeAmount = match ($calcType) {
            SharingFeeCalcType::DoctorFee => $feeBase,
            SharingFeeCalcType::Percentage => bcdiv(bcmul($feeBase, $rate, 4), '100', 2),
            SharingFeeCalcType::Fixed => bcmul($rate, (string) $quantity, 2),
        };

        return [
            'gross_base' => $grossBase,
            'material_deduction' => '0.00',
            'fee_base' => $feeBase,
            'fee_amount' => $feeAmount,
            'calc_type' => $calcType->value,
            'rate' => $rate,
            'base_source' => 'doctor_fee',
        ];
    }

    /**
     * Potongan bahan tidak dipakai lagi: BHP sudah terpisah di tarif.
     *
     * @deprecated
     */
    public function materialDeduction(SharingFeeRule $rule, string $grossBase, int $quantity): string
    {
        return '0.00';
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
