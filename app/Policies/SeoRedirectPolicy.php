<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesPlatformResource;

class SeoRedirectPolicy
{
    use AuthorizesPlatformResource;

    protected function viewPermission(): string
    {
        return 'seo.manage';
    }

    protected function managePermission(): string
    {
        return 'seo.manage';
    }
}
