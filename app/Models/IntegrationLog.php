<?php

namespace App\Models;

use App\Enums\IntegrationLogStatus;
use App\Enums\IntegrationProvider;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IntegrationLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'provider',
        'action',
        'status',
        'subject_type',
        'subject_id',
        'request_payload',
        'response_payload',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'provider' => IntegrationProvider::class,
            'status' => IntegrationLogStatus::class,
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
