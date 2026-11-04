<?php

namespace App\Models;

use App\Enums\ToothStatus;
use App\Models\Concerns\ScopedByPatientTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kondisi akhir satu gigi pada satu kunjungan. Tidak ditimpa oleh kunjungan berikutnya.
 */
class OdontogramSnapshot extends Model
{
    use ScopedByPatientTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'visit_id',
        'tooth_number',
        'status',
        'conditions',
        'notes',
        'recorded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'patient_id' => 'integer',
            'visit_id' => 'integer',
            'status' => ToothStatus::class,
            'conditions' => 'array',
            'recorded_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
