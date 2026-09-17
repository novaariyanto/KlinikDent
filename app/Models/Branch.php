<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    public const WEEK_DAYS = [
        'monday' => 'Senin',
        'tuesday' => 'Selasa',
        'wednesday' => 'Rabu',
        'thursday' => 'Kamis',
        'friday' => 'Jumat',
        'saturday' => 'Sabtu',
        'sunday' => 'Minggu',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'address',
        'phone',
        'opening_hours',
        'is_active',
        'queue_monitor_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'opening_hours' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Branch $branch) {
            if (empty($branch->queue_monitor_token)) {
                $branch->queue_monitor_token = Str::random(48);
            }
        });
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * @return HasMany<Visit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function monitorUrl(): string
    {
        return route('queue.monitor.public', $this->queue_monitor_token);
    }
}
