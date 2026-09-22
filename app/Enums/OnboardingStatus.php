<?php

namespace App\Enums;

enum OnboardingStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Belum dimulai',
            self::InProgress => 'Sedang disiapkan',
            self::Completed => 'Selesai',
        };
    }

    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }
}
