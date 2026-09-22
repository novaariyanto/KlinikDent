<?php

namespace App\Enums;

enum WhatsAppMessageStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Antrian',
            self::Processing => 'Diproses',
            self::Sent => 'Terkirim',
            self::Delivered => 'Diterima',
            self::Read => 'Dibaca',
            self::Failed => 'Gagal',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Queued => 'badge bg-secondary',
            self::Processing => 'badge bg-info',
            self::Sent => 'badge bg-primary',
            self::Delivered => 'badge bg-success',
            self::Read => 'badge bg-success',
            self::Failed => 'badge bg-danger',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Sent, self::Delivered, self::Read, self::Failed], true);
    }

    public function isSuccess(): bool
    {
        return in_array($this, [self::Sent, self::Delivered, self::Read], true);
    }
}
