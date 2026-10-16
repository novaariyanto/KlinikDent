<?php

namespace App\Policies;

use App\Models\SharingFeeSettlement;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;

class SharingFeeSettlementPolicy
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

    public function approve(User $user, SharingFeeSettlement $settlement): bool
    {
        return $user->can('sharing_fee.approve')
            && $user->belongsToTenantId($this->tenantIdOf($settlement))
            && $this->canAccessModelBranch($user, $settlement);
    }

    public function pay(User $user, SharingFeeSettlement $settlement): bool
    {
        return $user->can('sharing_fee.pay')
            && $user->belongsToTenantId($this->tenantIdOf($settlement))
            && $this->canAccessModelBranch($user, $settlement);
    }

    public function adjust(User $user, SharingFeeSettlement $settlement): bool
    {
        return $this->approve($user, $settlement);
    }
}
