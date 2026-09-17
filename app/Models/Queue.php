<?php

namespace App\Models;

use App\Enums\QueueStatus;
use Database\Factories\QueueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Queue extends Model
{
    /** @use HasFactory<QueueFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'visit_id',
        'queue_number',
        'called_at',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visit_id' => 'integer',
            'queue_number' => 'integer',
            'called_at' => 'datetime',
            'status' => QueueStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = current_tenant_id();

            if ($tenantId === null) {
                return;
            }

            $builder->whereHas('visit', function (Builder $query) use ($tenantId) {
                $query->withoutGlobalScopes()->where('tenant_id', $tenantId);
            });
        });
    }

    /**
     * @return BelongsTo<Visit, $this>
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function displayNumber(): string
    {
        return str_pad((string) $this->queue_number, 3, '0', STR_PAD_LEFT);
    }

    public function tenantId(): ?int
    {
        return $this->visit?->tenant_id
            ?? Visit::withoutGlobalScopes()->whereKey($this->visit_id)->value('tenant_id');
    }
}
