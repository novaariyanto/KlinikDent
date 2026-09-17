<?php

namespace App\Policies;

use App\Models\Service;
use App\Policies\Concerns\AuthorizesTenantResource;

class ServicePolicy
{
    use AuthorizesTenantResource;

    protected function viewPermission(): string
    {
        return 'service.view';
    }

    protected function managePermission(): string
    {
        return 'service.manage';
    }
}
