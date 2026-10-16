<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharingFeePayment extends Model
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'settlement_id',
        'expense_id',
        'cash_account_id',
        'amount',
        'method',
        'reference',
        'paid_on',
        'notes',
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
            'expense_id' => 'integer',
            'cash_account_id' => 'integer',
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'paid_on' => 'date',
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
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * @return BelongsTo<CashAccount, $this>
     */
    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
