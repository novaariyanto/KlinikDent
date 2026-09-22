<?php

namespace App\Models;

use App\Enums\WhatsAppCategory;
use App\Enums\WhatsAppEvent;
use App\Enums\WhatsAppMessageStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WhatsAppMessageLog extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_message_logs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'patient_id',
        'event_key',
        'category',
        'template_name',
        'phone_number',
        'to_name',
        'body_params',
        'status',
        'provider_message_id',
        'idempotency_key',
        'related_type',
        'related_id',
        'scheduled_type',
        'request_payload',
        'response_payload',
        'error_code',
        'error_message',
        'failed_at',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'patient_id' => 'integer',
            'event_key' => WhatsAppEvent::class,
            'category' => WhatsAppCategory::class,
            'body_params' => 'array',
            'status' => WhatsAppMessageStatus::class,
            'related_id' => 'integer',
            'request_payload' => 'array',
            'response_payload' => 'array',
            'failed_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<WhatsAppMessageLog>  $query
     * @return Builder<WhatsAppMessageLog>
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('created_at', now()->toDateString());
    }
}
