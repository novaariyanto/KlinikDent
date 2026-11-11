<?php

namespace App\Models;

use App\Enums\MedicationTypeCode;
use App\Enums\PrescriptionItemKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrescriptionTemplateItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'prescription_template_id',
        'sort',
        'kind',
        'medication_type',
        'medicine_id',
        'dosage',
        'frequency',
        'duration',
        'course',
        'unit',
        'quantity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prescription_template_id' => 'integer',
            'sort' => 'integer',
            'kind' => PrescriptionItemKind::class,
            'medication_type' => MedicationTypeCode::class,
            'medicine_id' => 'integer',
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PrescriptionTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(PrescriptionTemplate::class, 'prescription_template_id');
    }

    /**
     * @return BelongsTo<Medicine, $this>
     */
    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    /**
     * @return HasMany<PrescriptionTemplateIngredient, $this>
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(PrescriptionTemplateIngredient::class);
    }

    public function isCompound(): bool
    {
        return $this->kind === PrescriptionItemKind::Compound;
    }

    public function label(): string
    {
        if ($this->isCompound()) {
            $names = $this->relationLoaded('ingredients')
                ? $this->ingredients->pluck('name')->filter()->implode(', ')
                : '';

            return $names !== '' ? 'Racikan: '.$names : 'Racikan';
        }

        return $this->medicine?->name ?: 'Obat';
    }
}
