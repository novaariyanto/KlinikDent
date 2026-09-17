<?php

namespace App\Support\Clinical;

final class FdiTeeth
{
    /**
     * @return list<string>
     */
    public static function adult(): array
    {
        return [
            ...self::range(18, 11),
            ...self::range(21, 28),
            ...self::range(38, 31),
            ...self::range(41, 48),
        ];
    }

    /**
     * @return list<string>
     */
    public static function primary(): array
    {
        return [
            ...self::range(55, 51),
            ...self::range(61, 65),
            ...self::range(75, 71),
            ...self::range(81, 85),
        ];
    }

    /**
     * @return list<list<string>>
     */
    public static function primaryRows(): array
    {
        return [
            self::range(55, 51),
            self::range(61, 65),
            self::range(85, 81),
            self::range(71, 75),
        ];
    }

    public static function adultRows(): array
    {
        return [
            self::range(18, 11),
            self::range(21, 28),
            self::range(48, 41),
            self::range(31, 38),
        ];
    }

    public static function isValid(string $number): bool
    {
        return in_array($number, [...self::adult(), ...self::primary()], true);
    }

    public static function isPrimary(string $number): bool
    {
        return in_array($number, self::primary(), true);
    }

    public static function isUpper(string $number): bool
    {
        $quadrant = (int) substr($number, 0, 1);

        return in_array($quadrant, [1, 2, 5, 6], true);
    }

    public static function mesialOnRight(string $number): bool
    {
        $quadrant = (int) substr($number, 0, 1);

        return in_array($quadrant, [1, 4, 5, 8], true);
    }

    public static function kind(string $number): string
    {
        $position = (int) substr($number, 1, 1);

        if (self::isPrimary($number)) {
            return match ($position) {
                1, 2 => 'incisor',
                3 => 'canine',
                default => 'molar',
            };
        }

        return match ($position) {
            1, 2 => 'incisor',
            3 => 'canine',
            4, 5 => 'premolar',
            default => 'molar',
        };
    }

    public static function isAnterior(string $number): bool
    {
        return in_array(self::kind($number), ['incisor', 'canine'], true);
    }

    public static function surfaceHoverLabel(string $number, string $surface): string
    {
        $upper = self::isUpper($number);
        $anterior = self::isAnterior($number);

        $label = match ($surface) {
            'occlusal' => $anterior ? 'Incisal' : 'Occlusal',
            'mesial' => 'Mesial',
            'distal' => 'Distal',
            'buccal' => $anterior ? 'Labial' : 'Buccal',
            'palatal' => $upper ? 'Palatal' : 'Lingual',
            default => $surface,
        };

        return $number.' — '.$label;
    }

    public static function quadrant(string $number): int
    {
        return (int) substr($number, 0, 1);
    }

    /**
     * @return array<string, array{0: list<string>, 1: list<string>}>
     */
    public static function chart(): array
    {
        return [
            'upper_primary' => [self::range(55, 51), self::range(61, 65)],
            'upper_adult' => [self::range(18, 11), self::range(21, 28)],
            'lower_adult' => [self::range(48, 41), self::range(31, 38)],
            'lower_primary' => [self::range(85, 81), self::range(71, 75)],
        ];
    }

    /**
     * @return list<string>
     */
    protected static function range(int $from, int $to): array
    {
        $step = $from > $to ? -1 : 1;
        $numbers = [];

        for ($i = $from; $step > 0 ? $i <= $to : $i >= $to; $i += $step) {
            $numbers[] = (string) $i;
        }

        return $numbers;
    }
}
