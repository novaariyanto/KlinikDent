<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('billing.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('billing.view')
            && $user->belongsToTenantId($invoice->tenant_id)
            && $user->canAccessBranch((int) $invoice->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->can('billing.create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->can('billing.update')
            && $user->belongsToTenantId($invoice->tenant_id)
            && $user->canAccessBranch((int) $invoice->branch_id);
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $user->can('billing.void')
            && $user->belongsToTenantId($invoice->tenant_id)
            && $user->canAccessBranch((int) $invoice->branch_id)
            && ! $invoice->isVoid();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return false;
    }
}
