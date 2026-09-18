<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MedicineStockFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicineStock extends Model
{
    /** @use HasFactory<MedicineStockFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'medicine_id',
        'batch_number',
        'expired_date',
        'quantity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'medicine_id' => 'integer',
            'expired_date' => 'date',
            'quantity' => 'integer',
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
     * @return BelongsTo<Medicine, $this>
     */
    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isExpired(?\DateTimeInterface $at = null): bool
    {
        $at ??= now();

        return $this->expired_date?->toDateString() < $at->format('Y-m-d');
    }

    public function isExpiringSoon(int $days = 30, ?\DateTimeInterface $at = null): bool
    {
        if (! $this->expired_date) {
            return false;
        }

        $from = \Illuminate\Support\Carbon::parse($at ?? now())->startOfDay();
        $expired = $this->expired_date->copy()->startOfDay();

        return $expired->gte($from) && $expired->lte($from->copy()->addDays($days));
    }

    /**
     * @param  Builder<MedicineStock>  $query
     * @return Builder<MedicineStock>
     */
    public function scopeUsable(Builder $query, ?\DateTimeInterface $at = null): Builder
    {
        $date = ($at ?? now())->format('Y-m-d');

        return $query->where('quantity', '>', 0)->whereDate('expired_date', '>=', $date);
    }

    /**
     * @param  Builder<MedicineStock>  $query
     * @return Builder<MedicineStock>
     */
    public function scopeExpired(Builder $query, ?\DateTimeInterface $at = null): Builder
    {
        $date = ($at ?? now())->format('Y-m-d');

        return $query->whereDate('expired_date', '<', $date)->where('quantity', '>', 0);
    }

    /**
     * @param  Builder<MedicineStock>  $query
     * @return Builder<MedicineStock>
     */
    public function scopeExpiring(Builder $query, int $days = 30, ?\DateTimeInterface $at = null): Builder
    {
        $from = ($at ?? now())->format('Y-m-d');
        $until = now()->parse($from)->addDays($days)->toDateString();

        return $query->where('quantity', '>', 0)
            ->whereDate('expired_date', '>=', $from)
            ->whereDate('expired_date', '<=', $until);
    }
}
