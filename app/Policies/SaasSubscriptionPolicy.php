<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesPlatformResource;

class SaasSubscriptionPolicy
{
    use AuthorizesPlatformResource;

    protected function viewPermission(): string
    {
        return 'subscription.view';
    }

    protected function managePermission(): string
    {
        return 'subscription.manage';
    }
}
