<?php

namespace App\Models;

use App\Enums\PrescriptionItemKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrescriptionItem extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'prescription_id',
        'kind',
        'medicine_id',
        'dosage',
        'frequency',
        'duration',
        'quantity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prescription_id' => 'integer',
            'kind' => PrescriptionItemKind::class,
            'medicine_id' => 'integer',
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Prescription, $this>
     */
    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    /**
     * @return BelongsTo<Medicine, $this>
     */
    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    /**
     * @return HasMany<PrescriptionCompoundItem, $this>
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(PrescriptionCompoundItem::class);
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
