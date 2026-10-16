<?php

namespace App\Models;

use App\Enums\SharingFeeCalcType;
use App\Enums\SharingFeeMaterialDeductionType;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Finance\SharingFee\SharingFeeRuleResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharingFeeRule extends Model
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'doctor_user_id',
        'branch_id',
        'procedure_id',
        'calc_type',
        'rate',
        'material_deduction_type',
        'material_deduction_value',
        'priority',
        'effective_from',
        'effective_to',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'doctor_user_id' => 'integer',
            'branch_id' => 'integer',
            'procedure_id' => 'integer',
            'calc_type' => SharingFeeCalcType::class,
            'rate' => 'decimal:4',
            'material_deduction_type' => SharingFeeMaterialDeductionType::class,
            'material_deduction_value' => 'decimal:4',
            'priority' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
            'created_by' => 'integer',
            'updated_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Procedure, $this>
     */
    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isGeneral(): bool
    {
        return $this->doctor_user_id === null
            && $this->branch_id === null
            && $this->procedure_id === null;
    }

    public function coverageSummary(): string
    {
        $doctor = $this->doctor_user_id === null
            ? 'Semua Dokter'
            : ($this->doctor?->name ?? ('Dokter #'.$this->doctor_user_id));

        $branch = $this->branch_id === null
            ? 'Semua Cabang'
            : ($this->branch?->name ?? ('Cabang #'.$this->branch_id));

        $procedure = $this->procedure_id === null
            ? 'Semua Tindakan'
            : ($this->procedure?->name ?? ('Tindakan #'.$this->procedure_id));

        return $doctor.' · '.$branch.' · '.$procedure;
    }

    /**
     * @return array{key: string, label: string, class: string}
     */
    public function scheduleStatus(): array
    {
        if (! $this->is_active) {
            return ['key' => 'inactive', 'label' => 'Nonaktif', 'class' => 'badge bg-secondary'];
        }

        $today = now()->startOfDay();
        $from = $this->effective_from?->copy()->startOfDay();
        $to = $this->effective_to?->copy()->startOfDay();

        if ($from && $today->lt($from)) {
            return ['key' => 'upcoming', 'label' => 'Belum berlaku', 'class' => 'badge bg-info'];
        }

        if ($to && $today->gt($to)) {
            return ['key' => 'expired', 'label' => 'Kedaluwarsa', 'class' => 'badge bg-warning'];
        }

        return ['key' => 'active', 'label' => 'Aktif', 'class' => 'badge bg-success'];
    }

    public function specificityRank(): int
    {
        return app(SharingFeeRuleResolver::class)->specificityRank($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'id' => $this->id,
            'doctor_user_id' => $this->doctor_user_id,
            'branch_id' => $this->branch_id,
            'procedure_id' => $this->procedure_id,
            'coverage' => $this->coverageSummary(),
            'calc_type' => $this->calc_type?->value ?? $this->calc_type,
            'rate' => (string) $this->rate,
            'material_deduction_type' => $this->material_deduction_type?->value ?? $this->material_deduction_type,
            'material_deduction_value' => (string) $this->material_deduction_value,
            'priority' => (int) $this->priority,
            'specificity_rank' => $this->specificityRank(),
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
        ];
    }
}
