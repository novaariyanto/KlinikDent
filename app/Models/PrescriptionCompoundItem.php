<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionCompoundItem extends Model
{
    protected $fillable = [
        'prescription_item_id',
        'medicine_id',
        'name',
        'unit',
        'quantity',
        'strength_value',
        'strength_unit',
    ];

    protected function casts(): array
    {
        return [
            'prescription_item_id' => 'integer',
            'medicine_id' => 'integer',
            'quantity' => 'integer',
            'strength_value' => 'float',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class, 'prescription_item_id');
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}
