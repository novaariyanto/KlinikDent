<?php

namespace App\Support\Billing;

use App\Enums\ProcedureAdjustmentType;
use App\Enums\ProcedureDiscountType;

class ProcedureTariffCalculator
{
    /**
     * @param  array{doctor_fee?: string|int|float|null, bhp_amount?: string|int|float|null, clinic_fee?: string|int|float|null, price?: string|int|float|null}  $components
     * @return array{
     *     tariff_price: string,
     *     doctor_fee: string,
     *     bhp_amount: string,
     *     clinic_fee: string,
     *     adjustment_type: string,
     *     discount_type: string|null,
     *     discount_value: string,
     *     discount_amount: string,
     *     doctor_fee_charged: string,
     *     doctor_fee_waived: string,
     *     charged_unit_price: string
     * }
     */
    public function quote(
        array $components,
        ProcedureAdjustmentType $adjustment = ProcedureAdjustmentType::Normal,
        ?ProcedureDiscountType $discountType = null,
        string|int|float|null $discountValue = null,
    ): array {
        $normalized = $this->normalizeComponents($components);
        $discountValue = $this->normalize((string) ($discountValue ?? '0'));

        $doctorCharged = $normalized['doctor_fee'];
        $doctorWaived = '0.00';
        $discountAmount = '0.00';
        $charged = $normalized['tariff_price'];

        if ($adjustment === ProcedureAdjustmentType::DoctorFeeWaiver) {
            $doctorWaived = $normalized['doctor_fee'];
            $doctorCharged = '0.00';
            $charged = bcadd($normalized['bhp_amount'], $normalized['clinic_fee'], 2);
            $discountType = null;
            $discountValue = '0.00';
        } elseif ($adjustment === ProcedureAdjustmentType::ProcedureDiscount) {
            $discountType ??= ProcedureDiscountType::Nominal;
            $discountAmount = $this->discountAmount($normalized['tariff_price'], $discountType, $discountValue);
            $charged = bcsub($normalized['tariff_price'], $discountAmount, 2);

            if (bccomp($charged, '0', 2) < 0) {
                $charged = '0.00';
            }
        } else {
            $discountType = null;
            $discountValue = '0.00';
        }

        return [
            'tariff_price' => $normalized['tariff_price'],
            'doctor_fee' => $normalized['doctor_fee'],
            'bhp_amount' => $normalized['bhp_amount'],
            'clinic_fee' => $normalized['clinic_fee'],
            'adjustment_type' => $adjustment->value,
            'discount_type' => $discountType?->value,
            'discount_value' => $discountValue,
            'discount_amount' => $discountAmount,
            'doctor_fee_charged' => $doctorCharged,
            'doctor_fee_waived' => $doctorWaived,
            'charged_unit_price' => $charged,
        ];
    }

    /**
     * @param  array{doctor_fee?: string|int|float|null, bhp_amount?: string|int|float|null, clinic_fee?: string|int|float|null, price?: string|int|float|null}  $components
     * @return array{tariff_price: string, doctor_fee: string, bhp_amount: string, clinic_fee: string}
     */
    public function normalizeComponents(array $components): array
    {
        $doctor = $this->normalize((string) ($components['doctor_fee'] ?? '0'));
        $bhp = $this->normalize((string) ($components['bhp_amount'] ?? '0'));
        $clinic = $this->normalize((string) ($components['clinic_fee'] ?? '0'));
        $price = $this->normalize((string) ($components['price'] ?? '0'));
        $sum = bcadd(bcadd($doctor, $bhp, 2), $clinic, 2);

        if (bccomp($sum, '0', 2) === 0) {
            return [
                'tariff_price' => $price,
                'doctor_fee' => '0.00',
                'bhp_amount' => '0.00',
                'clinic_fee' => $price,
            ];
        }

        return [
            'tariff_price' => $sum,
            'doctor_fee' => $doctor,
            'bhp_amount' => $bhp,
            'clinic_fee' => $clinic,
        ];
    }

    public function discountAmount(string $base, ProcedureDiscountType $type, string $value): string
    {
        $base = $this->normalize($base);
        $value = $this->normalize($value);

        $amount = match ($type) {
            ProcedureDiscountType::Percent => bcdiv(bcmul($base, $value, 4), '100', 2),
            ProcedureDiscountType::Nominal => $value,
        };

        if (bccomp($amount, $base, 2) > 0) {
            return $base;
        }

        if (bccomp($amount, '0', 2) < 0) {
            return '0.00';
        }

        return $amount;
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
