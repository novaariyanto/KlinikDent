<?php

namespace App\Models;

use App\Enums\CashMutationType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CashMutation extends Model
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'cash_account_id',
        'type',
        'amount',
        'source_type',
        'source_id',
        'notes',
        'mutated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'cash_account_id' => 'integer',
            'type' => CashMutationType::class,
            'amount' => 'decimal:2',
            'source_id' => 'integer',
            'mutated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CashAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function signedAmount(): string
    {
        return $this->type === CashMutationType::Out
            ? bcmul((string) $this->amount, '-1', 2)
            : (string) $this->amount;
    }
}
