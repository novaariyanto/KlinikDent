<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ClinicSetting extends Model
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'key',
        'value',
    ];

    /**
     * @return array<string, string|null>
     */
    public static function valuesFor(int $tenantId): array
    {
        return Cache::rememberForever(static::cacheKey($tenantId), function () use ($tenantId) {
            return static::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->pluck('value', 'key')
                ->all();
        });
    }

    public static function getValue(string $key, mixed $default = null, ?int $tenantId = null): mixed
    {
        $tenantId ??= current_tenant_id();

        if (! $tenantId) {
            return $default;
        }

        $settings = static::valuesFor($tenantId);

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public static function setValue(string $key, mixed $value, ?int $tenantId = null): void
    {
        $tenantId ??= current_tenant_id();

        if (! $tenantId) {
            return;
        }

        static::query()->withoutGlobalScopes()->updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => $key],
            ['value' => $value]
        );

        Cache::forget(static::cacheKey($tenantId));
    }

    public static function cacheKey(int $tenantId): string
    {
        return 'clinic.settings.'.$tenantId;
    }
}
