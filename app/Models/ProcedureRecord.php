<?php

namespace App\Models;

use App\Enums\BillingStatus;
use App\Enums\DoctorFeeWaiverStatus;
use App\Enums\ProcedureAdjustmentType;
use App\Enums\ProcedureDiscountType;
use App\Models\Concerns\ScopedByVisitTenant;
use Database\Factories\ProcedureRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcedureRecord extends Model
{
    /** @use HasFactory<ProcedureRecordFactory> */
    use HasFactory, ScopedByVisitTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'visit_id',
        'procedure_id',
        'tooth_number',
        'icd9_code',
        'quantity',
        'price_at_time',
        'tariff_price_at_time',
        'doctor_fee_at_time',
        'bhp_amount_at_time',
        'clinic_fee_at_time',
        'adjustment_type',
        'discount_type',
        'discount_value',
        'discount_amount',
        'waiver_reason',
        'waived_by',
        'waived_at',
        'waiver_status',
        'approved_by',
        'approved_at',
        'include_waived_in_sharing_fee',
        'billing_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visit_id' => 'integer',
            'procedure_id' => 'integer',
            'quantity' => 'integer',
            'price_at_time' => 'decimal:2',
            'tariff_price_at_time' => 'decimal:2',
            'doctor_fee_at_time' => 'decimal:2',
            'bhp_amount_at_time' => 'decimal:2',
            'clinic_fee_at_time' => 'decimal:2',
            'adjustment_type' => ProcedureAdjustmentType::class,
            'discount_type' => ProcedureDiscountType::class,
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'waived_by' => 'integer',
            'waived_at' => 'datetime',
            'waiver_status' => DoctorFeeWaiverStatus::class,
            'approved_by' => 'integer',
            'approved_at' => 'datetime',
            'include_waived_in_sharing_fee' => 'boolean',
            'billing_status' => BillingStatus::class,
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
     * @return BelongsTo<Procedure, $this>
     */
    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function waivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by');
    }

    /**
     * @return HasMany<DoctorFeeWaiverLog, $this>
     */
    public function waiverLogs(): HasMany
    {
        return $this->hasMany(DoctorFeeWaiverLog::class);
    }

    public function originalLineTotal(): string
    {
        $base = (string) ($this->tariff_price_at_time ?: $this->price_at_time);

        return bcmul($base, (string) $this->quantity, 2);
    }

    public function waivedLineTotal(): string
    {
        if ($this->adjustment_type !== ProcedureAdjustmentType::DoctorFeeWaiver
            || $this->waiver_status === DoctorFeeWaiverStatus::Pending) {
            return '0.00';
        }

        return bcmul((string) $this->doctor_fee_at_time, (string) $this->quantity, 2);
    }

    public function discountLineTotal(): string
    {
        if ($this->adjustment_type !== ProcedureAdjustmentType::ProcedureDiscount) {
            return '0.00';
        }

        return bcmul((string) $this->discount_amount, (string) $this->quantity, 2);
    }

    public function adjustmentLabel(): string
    {
        $type = $this->adjustment_type instanceof ProcedureAdjustmentType
            ? $this->adjustment_type
            : ProcedureAdjustmentType::Normal;

        if ($type === ProcedureAdjustmentType::DoctorFeeWaiver
            && $this->waiver_status === DoctorFeeWaiverStatus::Pending) {
            return DoctorFeeWaiverStatus::Pending->label();
        }

        return $type->label();
    }

    public function lineTotal(): string
    {
        return bcmul((string) $this->price_at_time, (string) $this->quantity, 2);
    }

    public function isUnbilled(): bool
    {
        return $this->billing_status === BillingStatus::Unbilled;
    }
}
