<?php

namespace App\Models;

use App\Enums\SaasInvoiceStatus;
use Database\Factories\SaasInvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaasInvoice extends Model
{
    /** @use HasFactory<SaasInvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'number',
        'amount',
        'status',
        'issued_at',
        'due_at',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'subscription_id' => 'integer',
            'amount' => 'decimal:2',
            'status' => SaasInvoiceStatus::class,
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
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
     * @return BelongsTo<SaasSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SaasSubscription::class, 'subscription_id');
    }
}
