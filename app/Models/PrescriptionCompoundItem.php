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
        'role',
        'additive_kind',
        'quantity',
        'per_dose_quantity',
        'strength_value',
        'strength_unit',
    ];

    protected function casts(): array
    {
        return [
            'prescription_item_id' => 'integer',
            'medicine_id' => 'integer',
            'quantity' => 'integer',
            'per_dose_quantity' => 'integer',
            'strength_value' => 'float',
        ];
    }

    public function isAdditive(): bool
    {
        return $this->role === 'additive';
    }

    public function compositionLabel(): string
    {
        $parts = [$this->name];

        if ($this->isAdditive()) {
            $parts[] = match ($this->additive_kind) {
                'sakarin' => '(pemanis)',
                'flavor' => '(perasa)',
                'filler' => '(pengisi)',
                default => '(tambahan)',
            };
        }

        if ($this->strength_value) {
            $parts[] = trim($this->strength_value.' '.$this->strength_unit);
        }

        if ($this->per_dose_quantity) {
            $parts[] = $this->per_dose_quantity.' '.($this->unit ?: 'satuan').'/bungkus';
        }

        $parts[] = 'total '.$this->quantity.($this->unit ? ' '.$this->unit : '');

        return trim(implode(' ', array_filter($parts)));
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
