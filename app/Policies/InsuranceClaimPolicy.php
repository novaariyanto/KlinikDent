<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesTenantResource;

class InsuranceClaimPolicy
{
    use AuthorizesTenantResource;

    protected function viewPermission(): string
    {
        return 'integration.view';
    }

    protected function managePermission(): string
    {
        return 'integration.manage';
    }
}
