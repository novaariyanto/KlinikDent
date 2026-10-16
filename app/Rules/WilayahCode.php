<?php

namespace App\Rules;

use App\Support\Indonesia\WilayahService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class WilayahCode implements ValidationRule
{
    public function __construct(
        protected string $level,
        protected ?string $parentField = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $wilayah = app(WilayahService::class);
        $parent = $this->parentField ? request()->input($this->parentField) : null;

        $ok = match ($this->level) {
            'province' => $wilayah->isProvince((string) $value),
            'city' => $wilayah->isCity((string) $value, $parent !== null ? (string) $parent : null),
            'district' => $wilayah->isDistrict((string) $value, $parent !== null ? (string) $parent : null),
            'village' => $wilayah->isVillage((string) $value, $parent !== null ? (string) $parent : null),
            default => false,
        };

        if (! $ok) {
            $fail('Kode wilayah :attribute tidak valid.');
        }
    }
}
