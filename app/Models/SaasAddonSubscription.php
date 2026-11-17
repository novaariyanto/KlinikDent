<?php

namespace App\Models;

use App\Enums\AddonStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaasAddonSubscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'addon_id',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'activated_at',
        'suspended_at',
        'activated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'addon_id' => 'integer',
            'status' => AddonStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
            'activated_by' => 'integer',
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
     * @return BelongsTo<SaasAddon, $this>
     */
    public function addon(): BelongsTo
    {
        return $this->belongsTo(SaasAddon::class, 'addon_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    /**
     * @return HasMany<SaasAddonInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(SaasAddonInvoice::class, 'addon_subscription_id');
    }
}
