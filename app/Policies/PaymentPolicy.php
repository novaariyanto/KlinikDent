<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('payment.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        $payment->loadMissing('invoice');

        return $user->can('payment.view')
            && $user->belongsToTenantId($payment->invoice?->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->can('payment.create');
    }
}
