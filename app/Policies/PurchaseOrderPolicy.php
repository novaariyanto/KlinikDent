<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesTenantResource;

class PurchaseOrderPolicy
{
    use AuthorizesTenantResource;

    protected function viewPermission(): string
    {
        return 'purchase.view';
    }

    protected function managePermission(): string
    {
        return 'purchase.manage';
    }
}
