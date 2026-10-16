<?php

namespace App\Models;

use App\Enums\BpjsMembershipStatus;
use App\Enums\Citizenship;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Indonesia\WilayahService;
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
        'satusehat_id',
        'dob',
        'gender',
        'birth_place',
        'marital_status',
        'citizenship',
        'family_card_number',
        'phone',
        'marketing_consent',
        'marketing_consent_at',
        'address',
        'address_city',
        'address_postal_code',
        'province_code',
        'city_code',
        'district_code',
        'village_code',
        'rt',
        'rw',
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
            'marital_status' => MaritalStatus::class,
            'citizenship' => Citizenship::class,
            'marketing_consent' => 'boolean',
            'marketing_consent_at' => 'datetime',
            'bpjs_status' => BpjsMembershipStatus::class,
            'bpjs_checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Patient $patient) {
            if ($patient->isDirty('marketing_consent')) {
                $patient->marketing_consent_at = $patient->marketing_consent ? now() : null;
            }

            if ($patient->citizenship === null) {
                $patient->citizenship = Citizenship::Wni;
            }

            $patient->applyNikDerivedFields();
        });
    }

    /**
     * Isi wilayah / DOB / gender dari NIK bila masih kosong (semua jalur create/update).
     */
    public function applyNikDerivedFields(): void
    {
        $nik = preg_replace('/\D+/', '', (string) $this->nik) ?? '';

        if (strlen($nik) !== 16) {
            return;
        }

        $needsProvince = blank($this->province_code);
        $needsCity = blank($this->city_code);
        $needsDistrict = blank($this->district_code);
        $needsDob = $this->dob === null;
        $needsGender = $this->gender === null;
        $needsCityName = blank($this->address_city);

        if (! $needsProvince && ! $needsCity && ! $needsDistrict && ! $needsDob && ! $needsGender && ! $needsCityName) {
            return;
        }

        $result = app(WilayahService::class)->fromNik($nik);

        if (! ($result['ok'] ?? false)) {
            return;
        }

        if ($needsProvince) {
            $this->province_code = $result['province_code'] ?? null;
        }

        if ($needsCity) {
            $this->city_code = $result['city_code'] ?? null;
        }

        if ($needsDistrict) {
            $this->district_code = $result['district_code'] ?? null;
        }

        if ($needsCityName && filled($result['city_name'] ?? null)) {
            $this->address_city = $result['city_name'];
        }

        if ($needsDob && filled($result['dob'] ?? null)) {
            $this->dob = $result['dob'];
        }

        if ($needsGender && filled($result['gender'] ?? null)) {
            $this->gender = $result['gender'];
        }
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

    /**
     * Alamat administratif lengkap untuk POST Patient SATUSEHAT.
     */
    public function hasSatuSehatAddress(): bool
    {
        return filled($this->address)
            && filled($this->province_code)
            && filled($this->city_code)
            && filled($this->district_code)
            && filled($this->village_code)
            && filled($this->rt)
            && filled($this->rw);
    }

    /**
     * @return list<string>
     */
    public function missingSatuSehatAddressFields(): array
    {
        $map = [
            'alamat' => $this->address,
            'kode provinsi' => $this->province_code,
            'kode kab/kota' => $this->city_code,
            'kode kecamatan' => $this->district_code,
            'kode desa/kelurahan' => $this->village_code,
            'RT' => $this->rt,
            'RW' => $this->rw,
        ];

        return collect($map)
            ->reject(fn ($value) => filled($value))
            ->keys()
            ->values()
            ->all();
    }
}
