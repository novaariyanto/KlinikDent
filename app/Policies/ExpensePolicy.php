<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesTenantResource;

class ExpensePolicy
{
    use AuthorizesTenantResource;

    protected function viewPermission(): string
    {
        return 'expense.view';
    }

    protected function managePermission(): string
    {
        return 'expense.manage';
    }
}
