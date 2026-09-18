<?php

namespace App\Models;

use App\Enums\BillingInterval;
use Database\Factories\SaasPackageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasPackage extends Model
{
    /** @use HasFactory<SaasPackageFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'price',
        'interval',
        'trial_days',
        'max_branches',
        'max_users',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'interval' => BillingInterval::class,
            'trial_days' => 'integer',
            'max_branches' => 'integer',
            'max_users' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SaasSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(SaasSubscription::class, 'package_id');
    }

    public function isFree(): bool
    {
        return (float) $this->price <= 0;
    }
}
