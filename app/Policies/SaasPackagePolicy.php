<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesPlatformResource;

class SaasPackagePolicy
{
    use AuthorizesPlatformResource;

    protected function viewPermission(): string
    {
        return 'package.view';
    }

    protected function managePermission(): string
    {
        return 'package.manage';
    }
}
