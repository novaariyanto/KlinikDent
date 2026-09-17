<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesTenantResource;

class MedicinePolicy
{
    use AuthorizesTenantResource;

    protected function viewPermission(): string
    {
        return 'medicine.view';
    }

    protected function managePermission(): string
    {
        return 'medicine.manage';
    }
}
