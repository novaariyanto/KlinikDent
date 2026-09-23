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
            self::Small => 'Cocok untuk klinik gigi dengan tim kecil dan pekerjaan yang dapat dirangkap.',
            self::Medium => 'Cocok untuk klinik dengan beberapa dokter, tenaga medis, dan staf dengan tanggung jawab yang lebih terpisah.',
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
