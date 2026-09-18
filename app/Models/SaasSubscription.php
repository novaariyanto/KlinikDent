<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Database\Factories\SaasSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasSubscription extends Model
{
    /** @use HasFactory<SaasSubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'package_id',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'cancelled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'package_id' => 'integer',
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<SaasPackage, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(SaasPackage::class, 'package_id');
    }

    /**
     * @return HasMany<SaasInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(SaasInvoice::class, 'subscription_id');
    }

    public function isExpired(): bool
    {
        return $this->ends_at?->isPast() ?? true;
    }
}
