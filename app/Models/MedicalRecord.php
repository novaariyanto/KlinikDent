<?php

namespace App\Models;

use App\Models\Concerns\ScopedByVisitTenant;
use Database\Factories\MedicalRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        ];
    }
}
