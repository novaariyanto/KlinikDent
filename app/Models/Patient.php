<?php

namespace App\Models;

use App\Enums\BpjsMembershipStatus;
use App\Enums\Gender;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PatientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    /** @use HasFactory<PatientFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'medical_record_number',
        'name',
        'nik',
        'dob',
        'gender',
        'phone',
        'address',
        'default_payer_id',
        'bpjs_number',
        'bpjs_status',
        'bpjs_checked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'default_payer_id' => 'integer',
            'dob' => 'date',
            'gender' => Gender::class,
            'bpjs_status' => BpjsMembershipStatus::class,
            'bpjs_checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Payer, $this>
     */
    public function defaultPayer(): BelongsTo
    {
        return $this->belongsTo(Payer::class, 'default_payer_id');
    }

    /**
     * @return HasMany<Visit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    /**
     * @return HasMany<OdontogramTooth, $this>
     */
    public function odontogramTeeth(): HasMany
    {
        return $this->hasMany(OdontogramTooth::class);
    }

    /**
     * @return HasMany<OdontogramLog, $this>
     */
    public function odontogramLogs(): HasMany
    {
        return $this->hasMany(OdontogramLog::class);
    }

    /**
     * @param  Builder<Patient>  $query
     * @return Builder<Patient>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.$term.'%';

        return $query->where(function (Builder $inner) use ($like) {
            $inner->where('name', 'like', $like)
                ->orWhere('medical_record_number', 'like', $like)
                ->orWhere('nik', 'like', $like)
                ->orWhere('phone', 'like', $like);
        });
    }

    public function age(): ?int
    {
        return $this->dob?->age;
    }

    public function ageLabel(): string
    {
        $age = $this->age();

        return $age === null ? '-' : $age.' th';
    }
}
