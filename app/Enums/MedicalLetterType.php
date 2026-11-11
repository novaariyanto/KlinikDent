<?php

namespace App\Enums;

enum MedicalLetterType: string
{
    case Sick = 'sick';
    case Healthy = 'healthy';
    case Examination = 'examination';
    case Radiograph = 'radiograph';
    case Treatment = 'treatment';
    case Control = 'control';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Sick => 'Surat Keterangan Sakit',
            self::Healthy => 'Surat Keterangan Sehat',
            self::Examination => 'Surat Keterangan Pemeriksaan',
            self::Radiograph => 'Surat Pengantar Rontgen',
            self::Treatment => 'Surat Keterangan Perawatan',
            self::Control => 'Surat Keterangan Kontrol',
            self::Other => 'Surat Keterangan Medis Lainnya',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }
}
