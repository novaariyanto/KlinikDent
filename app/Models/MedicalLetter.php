<?php

namespace App\Models;

use App\Enums\MedicalLetterStatus;
use App\Enums\MedicalLetterType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalLetter extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'patient_id',
        'visit_id',
        'doctor_id',
        'created_by',
        'type',
        'number',
        'purpose',
        'body',
        'payload',
        'snapshot',
        'status',
        'signature_path',
        'issued_at',
        'cancelled_at',
        'cancel_reason',
        'revises_id',
        'revised_by_id',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'patient_id' => 'integer',
            'visit_id' => 'integer',
            'doctor_id' => 'integer',
            'created_by' => 'integer',
            'type' => MedicalLetterType::class,
            'payload' => 'array',
            'snapshot' => 'array',
            'status' => MedicalLetterStatus::class,
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'revises_id' => 'integer',
            'revised_by_id' => 'integer',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revises_id');
    }

    public function replacement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revised_by_id');
    }

    public function isIssued(): bool
    {
        return $this->status === MedicalLetterStatus::Issued;
    }
}
