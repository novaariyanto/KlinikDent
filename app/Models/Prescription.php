<?php

namespace App\Models;

use App\Enums\PrescriptionStatus;
use App\Models\Concerns\ScopedByVisitTenant;
use Database\Factories\PrescriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prescription extends Model
{
    /** @use HasFactory<PrescriptionFactory> */
    use HasFactory, ScopedByVisitTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'visit_id',
        'doctor_id',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visit_id' => 'integer',
            'doctor_id' => 'integer',
            'status' => PrescriptionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    /**
     * @return HasMany<PrescriptionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function isDraft(): bool
    {
        return $this->status === PrescriptionStatus::Draft;
    }

    public function isSent(): bool
    {
        return $this->status === PrescriptionStatus::Sent;
    }

    public function isFulfilled(): bool
    {
        return $this->status === PrescriptionStatus::Fulfilled;
    }
}
