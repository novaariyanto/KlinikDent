<?php

namespace App\Models;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function isDentist(): bool
    {
        return $this->hasRole(RoleName::Dentist);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeDoctors(Builder $query): Builder
    {
        return $query->role(RoleName::Dentist->value)->active();
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
