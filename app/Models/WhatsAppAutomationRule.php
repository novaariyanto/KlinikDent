<?php

namespace App\Models;

use App\Enums\WhatsAppEvent;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WhatsAppAutomationRule extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_automation_rules';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'event_key',
        'is_enabled',
        'config',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'event_key' => WhatsAppEvent::class,
            'is_enabled' => 'boolean',
            'config' => 'array',
        ];
    }

    public function configValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }
}
