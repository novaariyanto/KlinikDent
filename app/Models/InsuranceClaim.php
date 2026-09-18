<?php

namespace App\Models;

use App\Enums\InsuranceClaimStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\InsuranceClaimFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class InsuranceClaim extends Model
{
    /** @use HasFactory<InsuranceClaimFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'visit_id',
        'payer_id',
        'status',
        'amount',
        'document_path',
        'notes',
        'submitted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'patient_id' => 'integer',
            'visit_id' => 'integer',
            'payer_id' => 'integer',
            'status' => InsuranceClaimStatus::class,
            'amount' => 'decimal:2',
            'submitted_at' => 'datetime',
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
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * @return BelongsTo<Payer, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    public function documentUrl(): ?string
    {
        return $this->document_path ? Storage::url($this->document_path) : null;
    }
}
