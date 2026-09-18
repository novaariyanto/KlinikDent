<?php

namespace App\Models;

use App\Enums\CashShiftStatus;
use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CashierShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierShift extends Model
{
    /** @use HasFactory<CashierShiftFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'cashier_id',
        'opening_balance',
        'closing_balance',
        'system_balance',
        'variance',
        'close_notes',
        'opened_at',
        'closed_at',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'cashier_id' => 'integer',
            'opening_balance' => 'decimal:2',
            'closing_balance' => 'decimal:2',
            'system_balance' => 'decimal:2',
            'variance' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'status' => CashShiftStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'shift_id');
    }

    public function isOpen(): bool
    {
        return $this->status === CashShiftStatus::Open;
    }

    public function cashReceived(): string
    {
        return (string) $this->payments()
            ->where('method', PaymentMethod::Cash)
            ->sum('amount');
    }
}
