<?php

namespace App\Models;

use App\Enums\SharingFeeCalcType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharingFeeSettlementItem extends Model
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'settlement_id',
        'invoice_id',
        'invoice_item_id',
        'procedure_id',
        'rule_id',
        'invoice_number',
        'item_description',
        'quantity',
        'unit_price',
        'gross_base',
        'material_deduction',
        'fee_base',
        'calc_type',
        'rate',
        'fee_amount',
        'rule_priority',
        'rule_snapshot',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'settlement_id' => 'integer',
            'invoice_id' => 'integer',
            'invoice_item_id' => 'integer',
            'procedure_id' => 'integer',
            'rule_id' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'gross_base' => 'decimal:2',
            'material_deduction' => 'decimal:2',
            'fee_base' => 'decimal:2',
            'calc_type' => SharingFeeCalcType::class,
            'rate' => 'decimal:4',
            'fee_amount' => 'decimal:2',
            'rule_priority' => 'integer',
            'rule_snapshot' => 'array',
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
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<InvoiceItem, $this>
     */
    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    /**
     * @return BelongsTo<Procedure, $this>
     */
    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }

    /**
     * @return BelongsTo<SharingFeeRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(SharingFeeRule::class, 'rule_id');
    }
}
