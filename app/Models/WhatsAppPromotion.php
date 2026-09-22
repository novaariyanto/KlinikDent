<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppPromotion extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_promotions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'title',
        'message',
        'starts_at',
        'ends_at',
        'whatsapp_template_name',
        'is_enabled',
        'dispatched_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'is_enabled' => 'boolean',
            'dispatched_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isActiveOn(?string $date = null): bool
    {
        $date = $date ?: now()->toDateString();

        return $this->is_enabled
            && $this->starts_at?->toDateString() <= $date
            && $this->ends_at?->toDateString() >= $date;
    }
}
