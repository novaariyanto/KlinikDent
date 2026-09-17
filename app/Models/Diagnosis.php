<?php

namespace App\Models;

use App\Models\Concerns\ScopedByVisitTenant;
use Database\Factories\DiagnosisFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Diagnosis extends Model
{
    /** @use HasFactory<DiagnosisFactory> */
    use HasFactory, ScopedByVisitTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'visit_id',
        'tooth_number',
        'code',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visit_id' => 'integer',
        ];
    }
}
