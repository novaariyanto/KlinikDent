<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesTenantResource;

class ProcedurePolicy
{
    use AuthorizesTenantResource;

    protected function viewPermission(): string
    {
        return 'procedure.view';
    }

    protected function managePermission(): string
    {
        return 'clinic.manage';
    }
}
