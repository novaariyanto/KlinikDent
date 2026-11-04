<?php

namespace App\Enums;

enum ProcedureAdjustmentType: string
{
    case Normal = 'normal';
    case DoctorFeeWaiver = 'doctor_fee_waiver';
    case ProcedureDiscount = 'procedure_discount';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::DoctorFeeWaiver => 'Pembebasan jasa dokter',
            self::ProcedureDiscount => 'Diskon tindakan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Normal => 'bg-secondary',
            self::DoctorFeeWaiver => 'bg-info',
            self::ProcedureDiscount => 'bg-warning',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->all();
    }
}
