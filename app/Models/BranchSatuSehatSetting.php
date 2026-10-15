<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchSatuSehatSetting extends Model
{
    use BelongsToTenant;

    protected $table = 'branch_satusehat_settings';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'environment',
        'base_url',
        'token_url',
        'organization_id',
        'organization_name',
        'client_id',
        'client_secret',
        'location_id',
        'location_name',
        'connection_ok',
        'validated_at',
        'sync_enabled',
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
            'client_secret' => 'encrypted',
            'connection_ok' => 'boolean',
            'validated_at' => 'datetime',
            'sync_enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function hasSecret(): bool
    {
        return filled($this->client_secret);
    }

    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    public function baseUrl(): string
    {
        $configured = trim((string) $this->base_url);

        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        return $this->isProduction()
            ? 'https://api-satusehat.kemkes.go.id'
            : 'https://api-satusehat-stg.dto.kemkes.go.id';
    }

    public function tokenUrl(): string
    {
        $configured = trim((string) $this->token_url);

        if ($configured !== '') {
            return $configured;
        }

        return $this->baseUrl().'/oauth2/v1/accesstoken?grant_type=client_credentials';
    }

    public function readyToSync(): bool
    {
        return $this->sync_enabled
            && $this->connection_ok
            && filled($this->organization_id)
            && filled($this->location_id)
            && filled($this->client_id)
            && $this->hasSecret();
    }

    public function statusLabel(): string
    {
        if ($this->readyToSync()) {
            return 'Sinkronisasi aktif';
        }

        if ($this->connection_ok && ! filled($this->location_id)) {
            return 'Koneksi berhasil, lokasi belum dipilih';
        }

        if ($this->connection_ok) {
            return 'Terhubung, sinkronisasi belum diaktifkan';
        }

        if (filled($this->last_error)) {
            return 'Koneksi gagal';
        }

        return 'Belum diuji';
    }
}
