<?php

namespace App\Models;

use App\Enums\DiagnosisKind;
use App\Enums\DiagnosisRank;
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
        'kind',
        'rank',
        'tooth_number',
        'code',
        'description',
        'clinical_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visit_id' => 'integer',
            'kind' => DiagnosisKind::class,
            'rank' => DiagnosisRank::class,
        ];
    }

    public function displayLine(): string
    {
        $name = trim((filled($this->code) ? $this->code.' — ' : '').$this->description);
        $meta = collect([$this->kind?->label(), $this->rank?->label()])->filter()->implode(', ');

        return $meta !== '' ? $name.' ('.$meta.')' : $name;
    }
}
