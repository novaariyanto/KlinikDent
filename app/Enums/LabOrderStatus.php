<?php

namespace App\Enums;

enum LabOrderStatus: string
{
    case Ordered = 'ordered';
    case InProgress = 'in_progress';
    case Received = 'received';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Ordered => 'Dipesan',
            self::InProgress => 'Dikerjakan',
            self::Received => 'Diterima klinik',
            self::Delivered => 'Diserahkan',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Ordered => 'badge bg-info',
            self::InProgress => 'badge bg-warning',
            self::Received => 'badge bg-primary',
            self::Delivered => 'badge bg-success',
            self::Cancelled => 'badge bg-secondary',
        };
    }
}
