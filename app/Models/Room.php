<?php

namespace App\Models;

use App\Enums\RoomType;
use App\Models\Scopes\BranchTenantScope;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'code',
        'description',
        'phone',
        'email',
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
     * @return HasOne<SatuSehatResource, $this>
     */
    public function satusehatLocation(): HasOne
    {
        return $this->hasOne(SatuSehatResource::class, 'internal_id')
            ->where('internal_type', self::class)
            ->where('resource_type', 'Location');
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
