<?php

namespace App\Models;

use App\Enums\IntegrationProvider;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantIntegration extends Model
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'provider',
        'enabled',
        'fake',
        'credentials',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'provider' => IntegrationProvider::class,
            'enabled' => 'boolean',
            'fake' => 'boolean',
            'credentials' => 'encrypted:array',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function forTenant(int $tenantId, IntegrationProvider $provider): self
    {
        $existing = static::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('provider', $provider)
            ->first();

        if ($existing) {
            return $existing;
        }

        $model = new static;
        $model->tenant_id = $tenantId;
        $model->provider = $provider;
        $model->enabled = true;
        $model->fake = $provider === IntegrationProvider::SatuSehat
            ? (bool) config('integrations.satusehat.fake', true)
            : (bool) config('integrations.bpjs.fake', true);
        $model->credentials = [];

        return $model;
    }

    /**
     * Ciphertext yang tidak cocok dengan APP_KEY tidak boleh menjatuhkan halaman.
     * Array kosong membuat integrasi terlihat belum dikonfigurasi.
     */
    public function fromEncryptedString($value)
    {
        try {
            return parent::fromEncryptedString($value);
        } catch (DecryptException) {
            return '[]';
        }
    }

    public function credential(string $key, mixed $fallback = null): mixed
    {
        $stored = $this->credentials[$key] ?? null;

        return filled($stored) ? $stored : $fallback;
    }

    public function hasCredential(string $key): bool
    {
        return filled($this->credentials[$key] ?? null);
    }

    public function isConfigured(): bool
    {
        return match ($this->provider) {
            IntegrationProvider::SatuSehat => $this->hasCredential('client_id'),
            IntegrationProvider::Bpjs => $this->hasCredential('cons_id'),
            default => filled($this->credentials),
        };
    }

    public function statusLabel(): string
    {
        if (! $this->enabled) {
            return 'Nonaktif';
        }

        if ($this->fake) {
            return 'Sandbox lokal';
        }

        return $this->isConfigured() ? 'API terpasang' : 'API belum dikonfigurasi';
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @param  list<string>  $secretKeys
     * @return array<string, mixed>
     */
    public function mergeCredentials(array $incoming, array $secretKeys = []): array
    {
        $current = $this->credentials ?? [];

        foreach ($incoming as $key => $value) {
            if (in_array($key, $secretKeys, true) && ! filled($value)) {
                continue;
            }

            $current[$key] = $value === null ? null : (string) $value;
        }

        return $current;
    }
}
