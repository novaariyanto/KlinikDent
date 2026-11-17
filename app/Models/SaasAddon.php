<?php

namespace App\Models;

use App\Enums\AddonBillingInterval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasAddon extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'benefits',
        'price',
        'interval',
        'trial_days',
        'terms',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'benefits' => 'array',
            'price' => 'decimal:2',
            'interval' => AddonBillingInterval::class,
            'trial_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SaasAddonSubscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(SaasAddonSubscription::class, 'addon_id');
    }

    public function isFree(): bool
    {
        return (float) $this->price <= 0;
    }
}
