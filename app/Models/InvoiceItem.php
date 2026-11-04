<?php

namespace App\Models;

use App\Enums\InvoiceItemSource;
use App\Enums\ProcedureAdjustmentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'invoice_id',
        'source_type',
        'source_id',
        'description',
        'quantity',
        'unit_price',
        'subtotal',
        'adjustment_type',
        'original_unit_price',
        'doctor_fee_original',
        'doctor_fee_charged',
        'doctor_fee_waived',
        'bhp_amount',
        'clinic_fee',
        'discount_amount',
        'include_waived_in_sharing_fee',
        'component_snapshot',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'invoice_id' => 'integer',
            'source_type' => InvoiceItemSource::class,
            'source_id' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'adjustment_type' => ProcedureAdjustmentType::class,
            'original_unit_price' => 'decimal:2',
            'doctor_fee_original' => 'decimal:2',
            'doctor_fee_charged' => 'decimal:2',
            'doctor_fee_waived' => 'decimal:2',
            'bhp_amount' => 'decimal:2',
            'clinic_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'include_waived_in_sharing_fee' => 'boolean',
            'component_snapshot' => 'array',
        ];
    }

    public function usesDoctorFeeSharing(): bool
    {
        return bccomp($this->normalizeMoney((string) ($this->doctor_fee_original ?? '0')), '0', 2) > 0
            || bccomp($this->normalizeMoney((string) ($this->doctor_fee_charged ?? '0')), '0', 2) > 0
            || bccomp($this->normalizeMoney((string) ($this->doctor_fee_waived ?? '0')), '0', 2) > 0;
    }

    public function sharingDoctorFeeUnit(): string
    {
        if ($this->include_waived_in_sharing_fee) {
            return $this->normalizeMoney((string) ($this->doctor_fee_original ?? '0'));
        }

        return $this->normalizeMoney((string) ($this->doctor_fee_charged ?? '0'));
    }

    public function sharingDoctorFeeTotal(): string
    {
        return bcmul($this->sharingDoctorFeeUnit(), (string) max(1, (int) $this->quantity), 2);
    }

    public function sharingBhpTotal(): string
    {
        return bcmul($this->normalizeMoney((string) ($this->bhp_amount ?? '0')), (string) max(1, (int) $this->quantity), 2);
    }

    public function sharingFeeBase(): string
    {
        if ($this->usesDoctorFeeSharing()) {
            return $this->sharingDoctorFeeTotal();
        }

        if ($this->include_waived_in_sharing_fee && $this->original_unit_price) {
            return bcmul($this->normalizeMoney((string) $this->original_unit_price), (string) $this->quantity, 2);
        }

        return $this->normalizeMoney((string) $this->subtotal);
    }

    protected function normalizeMoney(string $amount): string
    {
        $amount = trim($amount);

        if ($amount === '' || ! is_numeric($amount)) {
            return '0.00';
        }

        return bcadd($amount, '0', 2);
    }

    public function adjustmentLabel(): ?string
    {
        $type = $this->adjustment_type instanceof ProcedureAdjustmentType
            ? $this->adjustment_type
            : ProcedureAdjustmentType::tryFrom((string) ($this->adjustment_type ?? ''));

        if (! $type || $type === ProcedureAdjustmentType::Normal) {
            return null;
        }

        return $type->label();
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
