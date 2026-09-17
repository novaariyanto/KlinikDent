<?php

namespace App\Models;

use App\Enums\RoomType;
use App\Models\Scopes\BranchTenantScope;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'name',
        'type',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'type' => RoomType::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new BranchTenantScope);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @param  Builder<Room>  $query
     * @return Builder<Room>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function tenantId(): ?int
    {
        return $this->branch?->tenant_id
            ?? Branch::withoutGlobalScopes()->whereKey($this->branch_id)->value('tenant_id');
    }
}
