<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesTenantResource;

class CashAccountPolicy
{
    use AuthorizesTenantResource;

    protected function viewPermission(): string
    {
        return 'cash_bank.view';
    }

    protected function managePermission(): string
    {
        return 'cash_bank.manage';
    }
}
