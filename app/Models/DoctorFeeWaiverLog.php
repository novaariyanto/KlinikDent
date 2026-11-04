<?php

namespace App\Models;

use App\Enums\DoctorFeeWaiverStatus;
use App\Enums\ProcedureAdjustmentType;
use App\Enums\ProcedureDiscountType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorFeeWaiverLog extends Model
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'visit_id',
        'procedure_record_id',
        'invoice_id',
        'doctor_user_id',
        'waived_by',
        'approved_by',
        'adjustment_type',
        'status',
        'reason',
        'doctor_fee_before',
        'doctor_fee_after',
        'tariff_price',
        'charged_unit_price',
        'discount_type',
        'discount_value',
        'discount_amount',
        'include_waived_in_sharing_fee',
        'waived_at',
        'approved_at',
        'snapshot',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'visit_id' => 'integer',
            'procedure_record_id' => 'integer',
            'invoice_id' => 'integer',
            'doctor_user_id' => 'integer',
            'waived_by' => 'integer',
            'approved_by' => 'integer',
            'adjustment_type' => ProcedureAdjustmentType::class,
            'status' => DoctorFeeWaiverStatus::class,
            'doctor_fee_before' => 'decimal:2',
            'doctor_fee_after' => 'decimal:2',
            'tariff_price' => 'decimal:2',
            'charged_unit_price' => 'decimal:2',
            'discount_type' => ProcedureDiscountType::class,
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'include_waived_in_sharing_fee' => 'boolean',
            'waived_at' => 'datetime',
            'approved_at' => 'datetime',
            'snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * @return BelongsTo<ProcedureRecord, $this>
     */
    public function procedureRecord(): BelongsTo
    {
        return $this->belongsTo(ProcedureRecord::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isPending(): bool
    {
        return $this->status === DoctorFeeWaiverStatus::Pending;
    }
}
