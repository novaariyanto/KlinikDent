<?php

namespace App\Models;

use App\Enums\ToothStatus;
use App\Models\Concerns\ScopedByVisitTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OdontogramLog extends Model
{
    use HasFactory, ScopedByVisitTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'visit_id',
        'tooth_number',
        'previous_status',
        'new_status',
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
            'recorded_by' => 'integer',
            'previous_status' => ToothStatus::class,
            'new_status' => ToothStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
