<?php

namespace App\Models;

use App\Enums\RoleName;
use App\Enums\VisitStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\VisitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Visit extends Model
{
    /** @use HasFactory<VisitFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'patient_id',
        'doctor_id',
        'room_id',
        'payer_id',
        'status',
        'visit_date',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'patient_id' => 'integer',
            'doctor_id' => 'integer',
            'room_id' => 'integer',
            'payer_id' => 'integer',
            'status' => VisitStatus::class,
            'visit_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<Payer, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Payer::class);
    }

    /**
     * @return HasOne<Queue, $this>
     */
    public function queue(): HasOne
    {
        return $this->hasOne(Queue::class);
    }

    /**
     * @return HasOne<MedicalRecord, $this>
     */
    public function medicalRecord(): HasOne
    {
        return $this->hasOne(MedicalRecord::class);
    }

    /**
     * @return HasMany<Diagnosis, $this>
     */
    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }

    /**
     * @return HasMany<ProcedureRecord, $this>
     */
    public function procedureRecords(): HasMany
    {
        return $this->hasMany(ProcedureRecord::class);
    }

    /**
     * @return HasMany<Prescription, $this>
     */
    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    /**
     * @return HasMany<Referral, $this>
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    /**
     * @return HasMany<OdontogramLog, $this>
     */
    public function odontogramLogs(): HasMany
    {
        return $this->hasMany(OdontogramLog::class);
    }

    /**
     * @param  Builder<Visit>  $query
     * @return Builder<Visit>
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('visit_date', now()->toDateString());
    }

    /**
     * @param  Builder<Visit>  $query
     * @return Builder<Visit>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isPlatformAdmin()) {
            return $query;
        }

        if ($user->branch_id && ! $user->can('branch.manage')) {
            $query->where('branch_id', $user->branch_id);
        }

        if ($user->hasRole(RoleName::Dentist) && ! $user->can('branch.manage')) {
            $query->where(function (Builder $inner) use ($user) {
                $inner->where('doctor_id', $user->id)->orWhereNull('doctor_id');
            });
        }

        return $query;
    }
}
