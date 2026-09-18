<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

enum Weekday: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public function label(): string
    {
        return match ($this) {
            self::Monday => 'Senin',
            self::Tuesday => 'Selasa',
            self::Wednesday => 'Rabu',
            self::Thursday => 'Kamis',
            self::Friday => 'Jumat',
            self::Saturday => 'Sabtu',
            self::Sunday => 'Minggu',
        };
    }

    public static function today(): self
    {
        return self::fromDate(now());
    }

    public static function fromDate(mixed $date): self
    {
        return self::from((int) Carbon::parse($date)->isoWeekday());
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $day) => [$day->value => $day->label()])
            ->all();
    }
}
