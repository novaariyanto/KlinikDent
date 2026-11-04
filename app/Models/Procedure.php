<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProcedureFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Procedure extends Model
{
    /** @use HasFactory<ProcedureFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'pemeriksaan' => 'Pemeriksaan',
        'konsultasi' => 'Konsultasi',
        'preventif' => 'Preventif',
        'konservasi' => 'Konservasi',
        'restorasi' => 'Restorasi',
        'endodonti' => 'Endodonti',
        'ekstraksi' => 'Ekstraksi',
        'bedah' => 'Bedah',
        'periodonti' => 'Periodonti',
        'prostodonti' => 'Prostodontik',
        'lainnya' => 'Lainnya',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'service_id',
        'code',
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
            'service_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return HasMany<Tariff, $this>
     */
    public function tariffs(): HasMany
    {
        return $this->hasMany(Tariff::class);
    }

    /**
     * @param  Builder<Procedure>  $query
     * @return Builder<Procedure>
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
