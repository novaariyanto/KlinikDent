<?php

namespace App\Enums;

enum Workspace: string
{
    case Platform = 'platform';
    case Admin = 'admin';
    case Operational = 'operational';
    case Clinical = 'clinical';
    case Default = 'default';

    public function label(): string
    {
        return match ($this) {
            self::Platform => 'Platform SaaS',
            self::Admin => 'Administrasi Klinik',
            self::Operational => 'Operasional Klinik',
            self::Clinical => 'Pelayanan Klinis',
            self::Default => 'Umum',
        };
    }
}
