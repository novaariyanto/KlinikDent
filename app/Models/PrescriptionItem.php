<?php

namespace App\Models;

use App\Enums\MedicationTypeCode;
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
        'medication_type',
        'medicine_id',
        'dosage',
        'frequency',
        'duration',
        'course',
        'quantity',
        'signa_p',
        'signa_s',
        'signa_sr',
        'signa_m',
        'waktu',
        'jho',
        'unit',
        'notes',
        'unit_price',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'prescription_id' => 'integer',
            'kind' => PrescriptionItemKind::class,
            'medication_type' => MedicationTypeCode::class,
            'medicine_id' => 'integer',
            'quantity' => 'integer',
            'jho' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    public function signaLine(): string
    {
        $parts = [
            $this->signa_p ?? '',
            $this->signa_s ?? '',
            $this->signa_sr ?? '',
            $this->signa_m ?? '',
        ];

        if (collect($parts)->filter(fn ($v) => $v !== '' && $v !== null)->isEmpty()) {
            return '';
        }

        return implode(' - ', array_map(fn ($v) => $v === '' || $v === null ? '0' : (string) $v, $parts));
    }

    public function subtotal(): float
    {
        return (float) $this->quantity * (float) ($this->unit_price ?? 0);
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

    public function instructionText(): string
    {
        return trim(implode(' · ', array_filter([
            $this->dosage,
            $this->frequency,
            $this->duration,
            $this->course,
        ])));
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
