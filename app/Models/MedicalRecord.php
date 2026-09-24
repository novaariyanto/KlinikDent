<?php

namespace App\Models;

use App\Models\Concerns\ScopedByVisitTenant;
use Database\Factories\MedicalRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicalRecord extends Model
{
    /** @use HasFactory<MedicalRecordFactory> */
    use HasFactory, ScopedByVisitTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'visit_id',
        'chief_complaint',
        'anamnesis',
        'clinical_notes',
        'initial_examination',
        'vital_signs',
        'care_notes',
        'dental_exam',
        'systemic_history',
        'plan_instructions',
        'control_date',
        'followup_date',
        'finalized_at',
        'finalized_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visit_id' => 'integer',
            'vital_signs' => 'array',
            'dental_exam' => 'array',
            'systemic_history' => 'array',
            'plan_instructions' => 'array',
            'control_date' => 'date',
            'followup_date' => 'date',
            'finalized_at' => 'datetime',
            'finalized_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (MedicalRecord $record): void {
            if (! $record->getOriginal('finalized_at')) {
                return;
            }

            $ignored = ['finalized_at', 'finalized_by', 'updated_at'];
            $changed = array_diff(array_keys($record->getDirty()), $ignored);

            if ($changed === [] || ! auth()->id()) {
                return;
            }

            MedicalRecordRevision::query()->create([
                'medical_record_id' => $record->id,
                'user_id' => auth()->id(),
                'snapshot' => collect($record->getOriginal())
                    ->map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('c') : $value)
                    ->all(),
            ]);
        });
    }

    public function amendments(): HasMany
    {
        return $this->hasMany(MedicalRecordAmendment::class);
    }

    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
