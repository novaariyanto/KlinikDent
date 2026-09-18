<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesTenantResource;

class MedicineStockPolicy
{
    use AuthorizesTenantResource;

    protected function viewPermission(): string
    {
        return 'stock.view';
    }

    protected function managePermission(): string
    {
        return 'stock.manage';
    }
}
