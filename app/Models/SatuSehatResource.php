<?php

namespace App\Models;

use App\Enums\SatuSehatStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SatuSehatResource extends Model
{
    use BelongsToTenant;

    protected $table = 'satusehat_resources';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'resource_type',
        'local_key',
        'internal_type',
        'internal_id',
        'satusehat_id',
        'sync_status',
        'last_synced_at',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'internal_id' => 'integer',
            'sync_status' => SatuSehatStatus::class,
            'last_synced_at' => 'datetime',
        ];
    }

    public function internal(): MorphTo
    {
        return $this->morphTo();
    }
}
