<?php

namespace App\Models;

use App\Enums\CashAccountType;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CashAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashAccount extends Model
{
    /** @use HasFactory<CashAccountFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'type',
        'balance',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'type' => CashAccountType::class,
            'balance' => 'decimal:2',
            'is_default' => 'boolean',
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
     * @return HasMany<CashMutation, $this>
     */
    public function mutations(): HasMany
    {
        return $this->hasMany(CashMutation::class);
    }

    public function mutationsSum(): string
    {
        $mutations = $this->relationLoaded('mutations')
            ? $this->mutations
            : $this->mutations()->get();

        return $mutations->reduce(
            fn (string $carry, CashMutation $mutation) => bcadd($carry, $mutation->signedAmount(), 2),
            '0.00'
        );
    }
}
