<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesTenantResource;

class TariffPolicy
{
    use AuthorizesTenantResource;

    protected function viewPermission(): string
    {
        return 'tariff.view';
    }

    protected function managePermission(): string
    {
        return 'tariff.manage';
    }
}
