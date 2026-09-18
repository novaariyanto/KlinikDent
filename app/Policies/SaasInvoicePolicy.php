<?php

namespace App\Policies;

use App\Policies\Concerns\AuthorizesPlatformResource;

class SaasInvoicePolicy
{
    use AuthorizesPlatformResource;

    protected function viewPermission(): string
    {
        return 'invoice.view';
    }

    protected function managePermission(): string
    {
        return 'subscription.manage';
    }
}
