<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppSetting extends Model
{
    use BelongsToTenant;

    protected $table = 'whatsapp_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'enabled',
        'api_base_url',
        'api_key',
        'phone_number',
        'business_name',
        'default_language',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'api_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'enabled' => 'boolean',
            'api_key' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function forTenant(int $tenantId): self
    {
        $existing = static::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->first();

        if ($existing) {
            return $existing;
        }

        $model = new static;
        $model->tenant_id = $tenantId;
        $model->enabled = false;
        $model->api_base_url = (string) config('services.whatsapp.base_url');
        $model->default_language = 'id';

        return $model;
    }

    public function isReady(): bool
    {
        return $this->enabled && filled($this->api_key) && filled($this->api_base_url);
    }

    public function maskedApiKey(): string
    {
        $key = (string) $this->api_key;

        if ($key === '') {
            return '';
        }

        $suffix = substr($key, -4);

        return str_repeat('•', max(8, strlen($key) - 4)).$suffix;
    }
}
