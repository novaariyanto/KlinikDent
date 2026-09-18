<?php

namespace App\Models;

use App\Enums\Weekday;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DoctorScheduleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorSchedule extends Model
{
    /** @use HasFactory<DoctorScheduleFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'doctor_id',
        'branch_id',
        'room_id',
        'weekday',
        'start_time',
        'end_time',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'doctor_id' => 'integer',
            'branch_id' => 'integer',
            'room_id' => 'integer',
            'weekday' => Weekday::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Doctor, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @param  Builder<DoctorSchedule>  $query
     * @return Builder<DoctorSchedule>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<DoctorSchedule>  $query
     * @return Builder<DoctorSchedule>
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->onDate(now());
    }

    /**
     * @param  Builder<DoctorSchedule>  $query
     * @return Builder<DoctorSchedule>
     */
    public function scopeOnDate(Builder $query, mixed $date): Builder
    {
        return $query->where('weekday', Weekday::fromDate($date)->value);
    }

    /**
     * @param  Builder<DoctorSchedule>  $query
     * @return Builder<DoctorSchedule>
     */
    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * @param  Builder<DoctorSchedule>  $query
     * @return Builder<DoctorSchedule>
     */
    public function scopeForRoom(Builder $query, ?int $roomId): Builder
    {
        if (! $roomId) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($roomId) {
            $inner->where('room_id', $roomId)->orWhereNull('room_id');
        });
    }

    public function timeRange(): string
    {
        return substr((string) $this->start_time, 0, 5).' – '.substr((string) $this->end_time, 0, 5);
    }

    public function overlaps(string $start, string $end, ?int $ignoreId = null): bool
    {
        $query = static::query()
            ->where('doctor_id', $this->doctor_id)
            ->where('branch_id', $this->branch_id)
            ->where('weekday', $this->weekday instanceof Weekday ? $this->weekday->value : $this->weekday)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start);

        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }
}
