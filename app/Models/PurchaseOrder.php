<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'supplier_id',
        'number',
        'status',
        'order_date',
        'received_at',
        'paid_at',
        'paid_from_account_id',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'supplier_id' => 'integer',
            'status' => PurchaseOrderStatus::class,
            'order_date' => 'date',
            'received_at' => 'datetime',
            'paid_at' => 'datetime',
            'paid_from_account_id' => 'integer',
            'created_by' => 'integer',
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
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * @return BelongsTo<CashAccount, $this>
     */
    public function paidFromAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'paid_from_account_id');
    }

    public function isDraft(): bool
    {
        return $this->status === PurchaseOrderStatus::Draft;
    }

    public function isOrdered(): bool
    {
        return $this->status === PurchaseOrderStatus::Ordered;
    }

    public function isReceived(): bool
    {
        return $this->status === PurchaseOrderStatus::Received;
    }

    public function isPayable(): bool
    {
        return $this->isReceived() && $this->paid_at === null;
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function totalAmount(): string
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        return $items->reduce(
            fn (string $carry, PurchaseOrderItem $item) => bcadd(
                $carry,
                bcmul((string) $item->unit_price, (string) $item->quantity, 2),
                2
            ),
            '0.00'
        );
    }
}
