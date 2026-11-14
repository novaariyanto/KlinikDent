<?php

namespace App\Support\Mobile;

use App\Exceptions\MobileApiException;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ActiveBranchResolver
{
    public function resolve(User $user, ?int $requestedId, bool $required): ?Branch
    {
        if (! $user->tenant_id) {
            throw new MobileApiException('Klinik tidak ditemukan.', 'tenant_required', 403);
        }

        if ($requestedId === null && ! $user->branch_id) {
            $branch = $this->accessibleBranches($user)->first();

            if (! $branch && $required) {
                throw new MobileApiException('Pilih cabang aktif.', 'branch_required', 403);
            }

            return $branch;
        }

        $candidateId = $requestedId ?? (int) $user->branch_id;

        if ($candidateId < 1) {
            throw new MobileApiException('Cabang tidak dapat diakses.', 'branch_forbidden', 403);
        }

        $branch = Branch::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey($candidateId)
            ->first();

        if (! $branch || ! $user->canAccessBranch((int) $branch->id)) {
            throw new MobileApiException('Cabang tidak dapat diakses.', 'branch_forbidden', 403);
        }

        if (! $branch->is_active) {
            throw new MobileApiException('Cabang tidak aktif.', 'branch_inactive', 403);
        }

        return $branch;
    }

    /**
     * @return Collection<int, Branch>
     */
    public function requestedId(?string $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return ctype_digit($raw) ? (int) $raw : 0;
    }

    public function accessibleBranches(User $user): Collection
    {
        return $this->allowedQuery($user)->get(['id', 'name', 'is_active']);
    }

    /**
     * @return Builder<Branch>
     */
    protected function allowedQuery(User $user): Builder
    {
        $query = Branch::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->orderBy('id');

        $restricted = $user->restrictedBranchIds();

        if ($restricted !== null) {
            $query->whereIn('id', $restricted);
        }

        return $query;
    }
}