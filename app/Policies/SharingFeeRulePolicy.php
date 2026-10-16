<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesTenantResource;

class SharingFeeRulePolicy
{
    use AuthorizesTenantResource;

    protected function viewPermission(): string
    {
        return 'sharing_fee.view';
    }

    protected function managePermission(): string
    {
        return 'sharing_fee.manage';
    }
}
