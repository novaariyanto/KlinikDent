<?php

namespace App\Models;

use App\Enums\WhatsAppCategory;
use App\Enums\WhatsAppEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppGlobalTemplate extends Model
{
    protected $table = 'whatsapp_global_templates';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_key',
        'category',
        'label',
        'default_template_name',
        'default_language',
        'preview_body',
        'default_parameter_map',
        'is_recommended',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_key' => WhatsAppEvent::class,
            'category' => WhatsAppCategory::class,
            'default_parameter_map' => 'array',
            'is_recommended' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<WhatsAppTenantTemplate, $this>
     */
    public function tenantTemplates(): HasMany
    {
        return $this->hasMany(WhatsAppTenantTemplate::class, 'global_template_id');
    }
}
