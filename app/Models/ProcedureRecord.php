<?php

namespace App\Models;

use App\Enums\BillingStatus;
use App\Models\Concerns\ScopedByVisitTenant;
use Database\Factories\ProcedureRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'quantity',
        'price_at_time',
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
            'billing_status' => BillingStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Procedure, $this>
     */
    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
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
