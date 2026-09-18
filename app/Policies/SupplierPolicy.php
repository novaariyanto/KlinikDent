<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesTenantResource;

class SupplierPolicy
{
    use AuthorizesTenantResource;

    protected function viewPermission(): string
    {
        return 'supplier.view';
    }

    protected function managePermission(): string
    {
        return 'supplier.manage';
    }
}
