<?php

namespace App\Models;

use App\Enums\RoleName;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DoctorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    /** @use HasFactory<DoctorFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'sip',
        'str',
        'specialization',
        'phone',
        'notes',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'user_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<DoctorSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(DoctorSchedule::class)->orderBy('weekday')->orderBy('start_time');
    }

    /**
     * @param  Builder<Doctor>  $query
     * @return Builder<Doctor>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function displayName(): string
    {
        return $this->user?->name ?: 'Tenaga medis #'.$this->id;
    }

    public function roleLabel(): string
    {
        $role = $this->user?->primaryRole();

        return $role?->label() ?? '-';
    }

    public function isDentist(): bool
    {
        return $this->user?->primaryRole() === RoleName::Dentist;
    }
}
