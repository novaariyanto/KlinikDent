<?php

namespace App\Models;

use App\Enums\SharingFeePeriodType;
use App\Enums\SharingFeeSettlementStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SharingFeeSettlement extends Model
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'doctor_user_id',
        'period_type',
        'period_start',
        'period_end',
        'status',
        'gross_fee_amount',
        'adjustment_amount',
        'net_fee_amount',
        'paid_amount',
        'created_by',
        'approved_by',
        'approved_at',
        'paid_by',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'doctor_user_id' => 'integer',
            'period_type' => SharingFeePeriodType::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'status' => SharingFeeSettlementStatus::class,
            'gross_fee_amount' => 'decimal:2',
            'adjustment_amount' => 'decimal:2',
            'net_fee_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'created_by' => 'integer',
            'approved_by' => 'integer',
            'approved_at' => 'datetime',
            'paid_by' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<SharingFeeSettlementItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SharingFeeSettlementItem::class, 'settlement_id');
    }

    /**
     * @return HasMany<SharingFeePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SharingFeePayment::class, 'settlement_id');
    }

    /**
     * @return HasMany<SharingFeeAdjustment, $this>
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(SharingFeeAdjustment::class, 'settlement_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function outstandingAmount(): string
    {
        $outstanding = bcsub((string) $this->net_fee_amount, (string) $this->paid_amount, 2);

        return bccomp($outstanding, '0', 2) < 0 ? '0.00' : $outstanding;
    }

    public function isDraft(): bool
    {
        return $this->status === SharingFeeSettlementStatus::Draft;
    }

    public function isApproved(): bool
    {
        return $this->status === SharingFeeSettlementStatus::Approved;
    }

    public function isPaid(): bool
    {
        return $this->status === SharingFeeSettlementStatus::Paid;
    }
}
