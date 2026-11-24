<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class IcdCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->replace('icd10_codes', public_path('icd/[PUBLIC] ICD-10 e-klaim.xlsx'));
        $this->replace('icd9_codes', public_path('icd/[PUBLIC] ICD-9CM e-klaim.xlsx'));

        Cache::forget('icd10_catalog:eklaim');
        Cache::forget('icd9_catalog:eklaim');
    }

    protected function replace(string $table, string $path): void
    {
        $rows = $this->rows($path);

        DB::table($table)->truncate();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    /**
     * @return list<array{code: string, description: string, version: ?string}>
     */
    protected function rows(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
        $mapped = [];

        foreach (array_slice($sheet, 1) as $row) {
            $code = trim((string) ($row[0] ?? ''));

            if ($code === '' || strcasecmp($code, 'CODE') === 0) {
                continue;
            }

            $mapped[$code] = [
                'code' => $code,
                'description' => trim((string) ($row[1] ?? '')),
                'version' => filled($row[2] ?? null) ? trim((string) $row[2]) : null,
            ];
        }

        return array_values($mapped);
    }
}
