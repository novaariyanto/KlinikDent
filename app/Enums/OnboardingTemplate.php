<?php

namespace App\Enums;

enum OnboardingTemplate: string
{
    case Small = 'small';
    case Medium = 'medium';

    public function label(): string
    {
        return match ($this) {
            self::Small => 'Klinik Kecil',
            self::Medium => 'Klinik Menengah / Besar',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Small => 'Tim kecil: biasanya 1 Admin Operasional (pendaftaran–farmasi–kasir) + 1 Dokter Gigi.',
            self::Medium => 'Beberapa dokter dan staf terpisah (pendaftaran, perawat, farmasi, kasir, keuangan).',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Small => 'bx bx-home',
            self::Medium => 'bx bx-building-house',
        };
    }
}
