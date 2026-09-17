<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MedicineFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    /** @use HasFactory<MedicineFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'antibiotik' => 'Antibiotik',
        'analgesik' => 'Analgesik',
        'antiseptik' => 'Antiseptik',
        'antiinflamasi' => 'Antiinflamasi',
        'lainnya' => 'Lainnya',
    ];

    /**
     * @var array<string, string>
     */
    public const UNITS = [
        'tablet' => 'Tablet',
        'kapsul' => 'Kapsul',
        'botol' => 'Botol',
        'tube' => 'Tube',
        'ampul' => 'Ampul',
        'sachet' => 'Sachet',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'unit',
        'category',
        'base_price',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Medicine>  $query
     * @return Builder<Medicine>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function unitLabel(): string
    {
        return self::UNITS[$this->unit] ?? $this->unit;
    }
}
