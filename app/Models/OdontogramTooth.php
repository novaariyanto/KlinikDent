<?php

namespace App\Models;

use App\Enums\ToothStatus;
use App\Models\Concerns\ScopedByPatientTenant;
use Database\Factories\OdontogramToothFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OdontogramTooth extends Model
{
    /** @use HasFactory<OdontogramToothFactory> */
    use HasFactory, ScopedByPatientTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'tooth_number',
        'status',
        'surfaces',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'patient_id' => 'integer',
            'status' => ToothStatus::class,
            'surfaces' => 'array',
        ];
    }
}
