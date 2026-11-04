<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\TariffFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tariff extends Model
{
    /** @use HasFactory<TariffFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'procedure_id',
        'payer_id',
        'price',
        'doctor_fee',
        'bhp_amount',
        'clinic_fee',
        'effective_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'procedure_id' => 'integer',
            'payer_id' => 'integer',
            'price' => 'decimal:2',
            'doctor_fee' => 'decimal:2',
            'bhp_amount' => 'decimal:2',
            'clinic_fee' => 'decimal:2',
            'effective_date' => 'date',
        ];
    }

    /**
     * @return array{price: string, doctor_fee: string, bhp_amount: string, clinic_fee: string}
     */
    public function componentBreakdown(): array
    {
        $components = app(\App\Support\Billing\ProcedureTariffCalculator::class)
            ->normalizeComponents([
                'doctor_fee' => $this->doctor_fee,
                'bhp_amount' => $this->bhp_amount,
                'clinic_fee' => $this->clinic_fee,
                'price' => $this->price,
            ]);

        return [
            'price' => $components['tariff_price'],
            'doctor_fee' => $components['doctor_fee'],
            'bhp_amount' => $components['bhp_amount'],
            'clinic_fee' => $components['clinic_fee'],
        ];
    }

    /**
     * @return BelongsTo<Procedure, $this>
     */
    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Payer, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }
}
