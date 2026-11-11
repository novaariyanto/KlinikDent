<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrescriptionTemplate extends Model
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'created_by',
        'name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<PrescriptionTemplateItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionTemplateItem::class)->orderBy('sort')->orderBy('id');
    }

    public function canBeManagedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return (int) $this->created_by === (int) $user->id
            || $user->can('prescription.update');
    }
}
