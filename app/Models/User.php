<?php

namespace App\Models;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasFactory, HasRoles, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'email',
        'password',
        'status',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isSuperAdmin(): bool
    {
        return $this->isPlatformAdmin();
    }

    public function isPlatformAdmin(): bool
    {
        return $this->hasRole(RoleName::SuperAdminSaas);
    }

    public function primaryRole(): ?RoleName
    {
        return RoleName::tryFromUserRoles($this->getRoleNames());
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withTimestamps();
    }

    /**
     * @return list<int>
     */
    public function assignedBranchIds(): array
    {
        $ids = $this->relationLoaded('branches')
            ? $this->branches->pluck('id')->all()
            : $this->branches()->pluck('branches.id')->all();

        $ids = array_map('intval', $ids);

        if ($this->branch_id) {
            $ids[] = (int) $this->branch_id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * null = tidak dibatasi (owner / platform). [0] = tidak punya cabang.
     *
     * @return list<int>|null
     */
    public function restrictedBranchIds(): ?array
    {
        if ($this->isPlatformAdmin() || $this->can('branch.manage')) {
            return null;
        }

        $ids = $this->assignedBranchIds();

        return $ids === [] ? [0] : $ids;
    }

    public function canAccessBranch(int $branchId): bool
    {
        $ids = $this->restrictedBranchIds();

        return $ids === null || in_array($branchId, $ids, true);
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    public function applyBranchLimit(Builder $query, string $column = 'branch_id', ?int $preferred = null): Builder
    {
        $ids = $this->restrictedBranchIds();

        if ($ids === null) {
            return $preferred ? $query->where($column, $preferred) : $query;
        }

        if ($preferred && in_array($preferred, $ids, true)) {
            return $query->where($column, $preferred);
        }

        return $query->whereIn($column, $ids);
    }

    /**
     * @param  list<int|string|null>  $ids
     */
    public function syncAssignedBranches(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        $this->branches()->sync($ids);

        $home = $ids[0] ?? null;

        if ((int) $this->branch_id !== (int) $home) {
            $this->forceFill(['branch_id' => $home])->saveQuietly();
        }
    }

    public function isDentist(): bool
    {
        return $this->hasRole(RoleName::Dentist);
    }

    public function isMedicalStaff(): bool
    {
        return $this->hasAnyRole(RoleName::medicalStaffValues());
    }

    /**
     * @return HasOne<Doctor, $this>
     */
    public function doctorProfile(): HasOne
    {
        return $this->hasOne(Doctor::class);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeDoctors(Builder $query): Builder
    {
        return $query->role(RoleName::Dentist->value)->active();
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeMedicalStaff(Builder $query): Builder
    {
        return $query->role(RoleName::medicalStaffValues());
    }

    public function belongsToTenantId(?int $tenantId): bool
    {
        if ($this->isPlatformAdmin()) {
            return true;
        }

        return $this->tenant_id !== null
            && $tenantId !== null
            && (int) $this->tenant_id === (int) $tenantId;
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Active);
    }
}
