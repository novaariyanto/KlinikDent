<?php

namespace App\Models;

use App\Enums\WhatsAppEvent;
use App\Models\Concerns\BelongsToTenant;
use App\Support\WhatsApp\WhatsAppParameterParser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppTenantTemplate extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_tenant_templates';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'global_template_id',
        'event_key',
        'whatsapp_template_name',
        'language_code',
        'preview_body',
        'parameter_map',
        'is_enabled',
        'remote_status',
        'remote_synced_at',
        'remote_meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'global_template_id' => 'integer',
            'event_key' => WhatsAppEvent::class,
            'parameter_map' => 'array',
            'is_enabled' => 'boolean',
            'remote_synced_at' => 'datetime',
            'remote_meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<WhatsAppGlobalTemplate, $this>
     */
    public function globalTemplate(): BelongsTo
    {
        return $this->belongsTo(WhatsAppGlobalTemplate::class, 'global_template_id');
    }

    /**
     * @return list<array{position: int, label: string, source: string}>
     */
    public function resolvedParameterMap(): array
    {
        return WhatsAppParameterParser::mergeMapping((string) $this->preview_body, $this->parameter_map ?? []);
    }

    public function parameterCount(): int
    {
        return count(WhatsAppParameterParser::positions((string) $this->preview_body));
    }

    public function isRemoteApproved(): bool
    {
        return ! filled($this->remote_status) || $this->remote_status === 'approved';
    }

    public function remoteDisplayName(): string
    {
        return (string) ($this->remote_meta['display_name'] ?? '');
    }
}
