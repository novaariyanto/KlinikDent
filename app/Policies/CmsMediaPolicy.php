<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesPlatformResource;

class CmsMediaPolicy
{
    use AuthorizesPlatformResource;

    protected function viewPermission(): string
    {
        return 'cms.view';
    }

    protected function managePermission(): string
    {
        return 'cms.manage';
    }
}
