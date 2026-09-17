<?php

namespace App\Enums;

enum MenuScope: string
{
    case Saas = 'saas';
    case Tenant = 'tenant';

    public function label(): string
    {
        return match ($this) {
            self::Saas => 'Platform SaaS',
            self::Tenant => 'Tenant / Klinik',
        };
    }
}
