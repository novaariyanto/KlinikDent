<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

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
            'connection_ok' => 'boolean',
            'validated_at' => 'datetime',
            'sync_enabled' => 'boolean',
        ];
    }

    /**
     * Cast encrypted melempar DecryptException jika APP_KEY tidak cocok.
     * Nilai yang gagal dibuka dianggap kosong supaya halaman pengaturan tetap bisa dibuka.
     */
    protected function clientSecret(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): ?string {
                if (! is_string($value) || $value === '') {
                    return null;
                }

                try {
                    return Crypt::decryptString($value);
                } catch (DecryptException) {
                    return null;
                }
            },
            set: function (mixed $value): ?string {
                if (! is_string($value) || trim($value) === '') {
                    return null;
                }

                return Crypt::encryptString($value);
            },
        );
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

    public function secretIsUnreadable(): bool
    {
        $raw = $this->getRawOriginal('client_secret');

        if (! is_string($raw) || $raw === '') {
            return false;
        }

        try {
            Crypt::decryptString($raw);

            return false;
        } catch (DecryptException) {
            return true;
        }
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

    /**
     * Kredensial + uji koneksi cukup untuk membuat/memperbarui Location cabang.
     */
    public function readyToPushLocation(): bool
    {
        return $this->connection_ok
            && filled($this->organization_id)
            && filled($this->client_id)
            && $this->hasSecret()
            && ! $this->secretIsUnreadable();
    }

    public function statusLabel(): string
    {
        if ($this->secretIsUnreadable()) {
            return 'Client secret tidak terbaca, isi ulang';
        }

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
