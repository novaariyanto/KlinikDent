<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MedicineFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medicine extends Model
{
    /** @use HasFactory<MedicineFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Kategori terapi obat.
     *
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'analgesik_antipiretik' => 'Analgesik dan antipiretik',
        'antibiotik' => 'Antibiotik',
        'antihistamin_antialergi' => 'Antihistamin dan antialergi',
        'antihipertensi' => 'Antihipertensi',
        'antidiabetes' => 'Antidiabetes',
        'antiinflamasi' => 'Antiinflamasi',
        'antasida_saluran_cerna' => 'Antasida dan obat saluran cerna',
        'antitusif_ekspektoran' => 'Antitusif dan ekspektoran',
        'antijamur' => 'Antijamur',
        'antivirus' => 'Antivirus',
        'vitamin_suplemen' => 'Vitamin dan suplemen',
        'anestesi' => 'Anestesi',
        'obat_kulit' => 'Obat kulit (dermatologis)',
        'obat_mata' => 'Obat mata (oftalmik)',
        'obat_tht' => 'Obat THT',
        'obat_saluran_pernapasan' => 'Obat saluran pernapasan',
        'obat_hormonal' => 'Obat hormonal',
        'obat_kardiovaskular' => 'Obat kardiovaskular',
        'obat_saraf_psikiatri' => 'Obat saraf dan psikiatri',
        'lainnya' => 'Lainnya',
    ];

    /**
     * Alias kategori lama → key baru (kompatibilitas data existing).
     *
     * @var array<string, string>
     */
    public const CATEGORY_ALIASES = [
        'analgesik' => 'analgesik_antipiretik',
        'antiseptik' => 'lainnya',
    ];

    /**
     * @var array<string, string>
     */
    public const UNITS = [
        'tablet' => 'Tablet',
        'injeksi' => 'Injeksi',
        'kapsul' => 'Kapsul',
        'infus' => 'Infus',
        'unit' => 'Unit',
        'sirup' => 'Sirup',
        'tetes_mata' => 'Tetes mata',
        'kaplet' => 'Kaplet',
        'krim' => 'Krim',
        'suppositoria' => 'Suppositoria',
        'tetes' => 'Tetes',
        'salep' => 'Salep',
        'inhaler' => 'Inhaler',
        'tetes_telinga' => 'Tetes telinga',
        'nebul' => 'Nebul',
        'semprot' => 'Semprot',
        // kompatibilitas data lama
        'botol' => 'Botol',
        'tube' => 'Tube',
        'ampul' => 'Ampul',
        'sachet' => 'Sachet',
    ];

    /**
     * @var array<string, string>
     */
    public const MARGIN_TYPES = [
        'fixed' => 'Harga tetap',
        'percent' => 'Persentase',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'kfa_code',
        'strength_value',
        'strength_unit',
        'unit',
        'category',
        'base_price',
        'margin_type',
        'margin_value',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'strength_value' => 'float',
            'base_price' => 'decimal:2',
            'margin_value' => 'decimal:2',
            'sell_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Medicine $medicine): void {
            if ($medicine->kfa_code === '') {
                $medicine->kfa_code = null;
            }

            if ($medicine->strength_unit === '') {
                $medicine->strength_unit = null;
            }

            if ($medicine->strength_value === '' || $medicine->strength_value === null) {
                $medicine->strength_value = null;
            }

            if ($medicine->unit === '') {
                $medicine->unit = null;
            }

            $medicine->category = filled($medicine->category)
                ? self::normalizeCategoryKey((string) $medicine->category)
                : null;

            $type = filled($medicine->margin_type) && $medicine->margin_type === 'percent'
                ? 'percent'
                : 'fixed';
            $base = number_format((float) ($medicine->base_price ?? 0), 2, '.', '');
            $margin = number_format((float) ($medicine->margin_value ?? 0), 2, '.', '');

            $medicine->margin_type = $type;
            $medicine->base_price = $base;
            $medicine->margin_value = $margin;
            $medicine->sell_price = self::calculateSellPrice($base, $type, $margin);
        });
    }

    public function strengthLabel(): ?string
    {
        if ($this->strength_value === null || ! filled($this->strength_unit)) {
            return null;
        }

        $formatted = number_format((float) $this->strength_value, 4, '.', '');
        $value = rtrim(rtrim($formatted, '0'), '.');
        if ($value === '') {
            $value = '0';
        }

        return $value.' '.$this->strength_unit;
    }

    /**
     * Label katalog untuk select / daftar farmasi (nama + unit + KFA + kekuatan).
     */
    public function catalogLabel(bool $withUnit = true): string
    {
        $label = (string) $this->name;

        if ($withUnit) {
            $label .= ' ('.$this->unitLabel().')';
        }

        if (filled($this->kfa_code)) {
            $label .= ' · KFA '.$this->kfa_code;
        }

        if ($strength = $this->strengthLabel()) {
            $label .= ' · '.$strength;
        }

        return $label;
    }

    public static function calculateSellPrice(string $base, string $type, string $margin): string
    {
        if ($type === 'percent') {
            $markup = bcmul($base, bcdiv($margin, '100', 6), 2);

            return bcadd($base, $markup, 2);
        }

        return bcadd($base, $margin, 2);
    }

    public function marginLabel(): string
    {
        $value = number_format((float) $this->margin_value, 0, ',', '.');

        return $this->margin_type === 'percent'
            ? $value.'%'
            : 'Rp '.$value;
    }

    /**
     * @param  Builder<Medicine>  $query
     * @return Builder<Medicine>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function categoryLabel(): string
    {
        if (! filled($this->category)) {
            return '—';
        }

        $key = self::normalizeCategoryKey((string) $this->category);

        return $key ? (self::CATEGORIES[$key] ?? $this->category) : '—';
    }

    public static function normalizeCategoryKey(?string $category): ?string
    {
        $key = strtolower(trim((string) $category));

        if ($key === '') {
            return null;
        }

        if (isset(self::CATEGORY_ALIASES[$key])) {
            return self::CATEGORY_ALIASES[$key];
        }

        if (array_key_exists($key, self::CATEGORIES)) {
            return $key;
        }

        foreach (self::CATEGORIES as $slug => $label) {
            if (strtolower($label) === $key) {
                return $slug;
            }
        }

        return null;
    }

    public function unitLabel(): string
    {
        if (! filled($this->unit)) {
            return '—';
        }

        return self::UNITS[$this->unit] ?? (string) $this->unit;
    }

    /**
     * @return HasMany<MedicineStock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(MedicineStock::class);
    }
}
