<?php

namespace App\Models;

use App\Enums\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'subdomain',
        'status',
        'plan_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'plan_id' => 'integer',
        ];
    }

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return BelongsTo<SaasPackage, $this>
     */
    public function package(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(SaasPackage::class, 'plan_id');
    }

    /**
     * @return HasMany<TenantIntegration, $this>
     */
    public function integrations(): HasMany
    {
        return $this->hasMany(TenantIntegration::class);
    }

    /**
     * @return HasMany<SaasSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(SaasSubscription::class);
    }

    public function currentSubscription(): ?SaasSubscription
    {
        return $this->subscriptions()
            ->with('package')
            ->latest('id')
            ->first();
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active || $this->status === TenantStatus::Trial;
    }
}
