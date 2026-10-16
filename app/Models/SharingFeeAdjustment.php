<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharingFeeAdjustment extends Model
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'settlement_id',
        'settlement_item_id',
        'amount',
        'reason',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'settlement_id' => 'integer',
            'settlement_item_id' => 'integer',
            'amount' => 'decimal:2',
            'created_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SharingFeeSettlement, $this>
     */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(SharingFeeSettlement::class, 'settlement_id');
    }

    /**
     * @return BelongsTo<SharingFeeSettlementItem, $this>
     */
    public function settlementItem(): BelongsTo
    {
        return $this->belongsTo(SharingFeeSettlementItem::class, 'settlement_item_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
