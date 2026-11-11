<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionTemplateIngredient extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'prescription_template_item_id',
        'medicine_id',
        'name',
        'unit',
        'role',
        'additive_kind',
        'quantity',
        'per_dose_quantity',
        'strength_value',
        'strength_unit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prescription_template_item_id' => 'integer',
            'medicine_id' => 'integer',
            'quantity' => 'integer',
            'per_dose_quantity' => 'integer',
            'strength_value' => 'float',
        ];
    }

    /**
     * @return BelongsTo<PrescriptionTemplateItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(PrescriptionTemplateItem::class, 'prescription_template_item_id');
    }

    /**
     * @return BelongsTo<Medicine, $this>
     */
    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}
