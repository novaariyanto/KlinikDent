<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'konsultasi' => 'Konsultasi',
        'preventif' => 'Preventif',
        'restorasi' => 'Restorasi',
        'bedah' => 'Bedah',
        'ortodonti' => 'Ortodonti',
        'lainnya' => 'Lainnya',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'category',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Procedure, $this>
     */
    public function procedures(): HasMany
    {
        return $this->hasMany(Procedure::class);
    }

    /**
     * @param  Builder<Service>  $query
     * @return Builder<Service>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }
}
