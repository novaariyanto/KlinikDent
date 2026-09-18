<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesTenantResource;

class CashierShiftPolicy
{
    use AuthorizesTenantResource;

    protected function viewPermission(): string
    {
        return 'cash_shift.view';
    }

    protected function managePermission(): string
    {
        return 'cash_shift.manage';
    }
}
