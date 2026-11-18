<?php

namespace App\Enums;

enum EducationLevel: string
{
    case None = 'none';
    case IncompleteElementary = 'incomplete_elementary';
    case Elementary = 'elementary';
    case JuniorHigh = 'junior_high';
    case SeniorHigh = 'senior_high';
    case Diploma = 'diploma';
    case Bachelor = 'bachelor';
    case Master = 'master';
    case Doctorate = 'doctorate';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Tidak sekolah',
            self::IncompleteElementary => 'Tidak tamat SD/sederajat',
            self::Elementary => 'Tamat SD/sederajat',
            self::JuniorHigh => 'Tamat SMP/sederajat',
            self::SeniorHigh => 'Tamat SMA/sederajat',
            self::Diploma => 'Tamat D1–D3/sederajat',
            self::Bachelor => 'Tamat D4/S1/sederajat',
            self::Master => 'Tamat S2/sederajat',
            self::Doctorate => 'Tamat S3/sederajat',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $item) => [$item->value => $item->label()])
            ->all();
    }
}
