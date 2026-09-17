<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'prescription_id',
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
}
