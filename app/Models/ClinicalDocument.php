<?php

namespace App\Models;

use App\Enums\ClinicalDocumentStatus;
use App\Enums\ClinicalDocumentType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ClinicalDocument extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'visit_id',
        'type',
        'number',
        'verification_code',
        'patient_initials',
        'payload',
        'status',
        'signed_at',
        'signature_path',
        'signature_provider',
        'certificate_sn',
        'signed_payload',
        'reference_type',
        'reference_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => ClinicalDocumentType::class,
            'status' => ClinicalDocumentStatus::class,
            'payload' => 'array',
            'signed_at' => 'datetime',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function isValid(): bool
    {
        return $this->status === ClinicalDocumentStatus::Valid;
    }
}
