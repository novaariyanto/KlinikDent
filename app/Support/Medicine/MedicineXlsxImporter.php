<?php

namespace App\Support\Medicine;

use App\Models\Medicine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MedicineXlsxImporter
{
    public const HEADERS = [
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

    public function templateResponse(string $filename = 'template-obat.xlsx'): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Obat');
        $sheet->fromArray([self::HEADERS], null, 'A1');
        $sheet->fromArray([
            ['Paracetamol 500mg', '93004418', 500, 'mg', 'tablet', 'analgesik_antipiretik', 1500, 'fixed', 500, 1],
            ['Amoxicillin 500mg', '', 500, 'mg', 'kapsul', 'antibiotik', 2500, 'percent', 10, 1],
        ], null, 'A2');

        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return list<array{row: int, data: array<string, mixed>}>
     */
    public function parse(UploadedFile $file): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'file' => 'File XLSX tidak dapat dibaca.',
            ]);
        }

        return $this->parseSheetRows(
            $spreadsheet->getActiveSheet()->toArray(null, true, true, false)
        );
    }

    /**
     * @return list<array{row: int, data: array<string, mixed>}>
     */
    public function parsePath(string $path): array
    {
        if (! is_file($path)) {
            throw ValidationException::withMessages([
                'file' => 'File master obat tidak ditemukan.',
            ]);
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'file' => 'File master obat tidak dapat dibaca.',
            ]);
        }

        return $this->parseSheetRows(
            $spreadsheet->getActiveSheet()->toArray(null, true, true, false)
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function masterCatalogRows(): array
    {
        return array_map(
            fn (array $row) => $row['data'],
            $this->parsePath(base_path('database/master_obat.xlsx'))
        );
    }

    /**
     * @param  list<list<mixed>|null>  $rows
     * @return list<array{row: int, data: array<string, mixed>}>
     */
    protected function parseSheetRows(array $rows): array
    {
        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => 'File XLSX kosong.',
            ]);
        }

        $headers = $this->normalizeHeaders(array_map(fn ($value) => (string) $value, $rows[0] ?? []));
        $this->assertRequiredHeaders($headers);

        $parsed = [];

        foreach (array_slice($rows, 1) as $index => $row) {
            $excelRow = $index + 2;
            $mapped = $this->mapRow($headers, is_array($row) ? $row : []);
            if ($this->rowIsEmpty($mapped)) {
                continue;
            }

            $parsed[] = [
                'row' => $excelRow,
                'data' => $this->normalizePayload($mapped),
            ];
        }

        if ($parsed === []) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada baris data obat pada file.',
            ]);
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: string, message: string, medicine_id?: int}
     */
    public function importOne(array $payload, int $tenantId): array
    {
        $payload = $this->normalizePayload($payload);
        $validator = Validator::make($payload, $this->rules());

        if ($validator->fails()) {
            return [
                'status' => 'failed',
                'message' => $validator->errors()->first(),
            ];
        }

        $data = $validator->validated();

        $exists = Medicine::query()
            ->where('tenant_id', $tenantId)
            ->where('name', $data['name'])
            ->exists();

        if ($exists) {
            return [
                'status' => 'skipped',
                'message' => 'Nama obat sudah ada.',
            ];
        }

        if (filled($data['kfa_code'] ?? null)) {
            $kfaExists = Medicine::query()
                ->where('tenant_id', $tenantId)
                ->where('kfa_code', $data['kfa_code'])
                ->exists();

            if ($kfaExists) {
                return [
                    'status' => 'failed',
                    'message' => 'Kode KFA sudah dipakai obat lain.',
                ];
            }
        }

        $medicine = Medicine::query()->create(array_merge($data, [
            'tenant_id' => $tenantId,
        ]));

        activity_log(
            'created',
            $medicine,
            $data,
            'Imported medicine '.$medicine->name,
            'medicines'
        );

        return [
            'status' => 'created',
            'message' => 'Obat ditambahkan.',
            'medicine_id' => $medicine->id,
        ];
    }

    /**
     * @param  list<string|null>  $headerRow
     * @return list<string>
     */
    protected function normalizeHeaders(array $headerRow): array
    {
        return array_map(function ($header) {
            $value = strtolower(trim((string) $header));
            $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

            return $value;
        }, $headerRow);
    }

    /**
     * @param  list<string>  $headers
     */
    protected function assertRequiredHeaders(array $headers): void
    {
        $missing = array_values(array_diff(['name'], $headers));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => 'Kolom wajib tidak ditemukan: '.implode(', ', $missing).'.',
            ]);
        }
    }

    protected function normalizeStrengthUnit(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $unit = trim((string) $value);
        if ($unit === '' || $unit === '-' || strtolower($unit) === 'null') {
            return null;
        }

        return $unit;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<mixed>  $row
     * @return array<string, mixed>
     */
    protected function mapRow(array $headers, array $row): array
    {
        $payload = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $payload[$header] = $row[$index] ?? null;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizePayload(array $payload): array
    {
        $isActive = $payload['is_active'] ?? '1';
        if (is_string($isActive)) {
            $normalized = strtolower(trim($isActive));
            $isActive = in_array($normalized, ['1', 'true', 'ya', 'yes', 'aktif'], true);
        } elseif (is_numeric($isActive)) {
            $isActive = (int) $isActive === 1;
        }

        return [
            'name' => isset($payload['name']) ? trim((string) $payload['name']) : null,
            'kfa_code' => filled($payload['kfa_code'] ?? null)
                ? preg_replace('/\s+/', '', (string) $payload['kfa_code'])
                : null,
            'strength_value' => ($payload['strength_value'] ?? '') === '' || $payload['strength_value'] === null
                ? null
                : $payload['strength_value'],
            'strength_unit' => $this->normalizeStrengthUnit($payload['strength_unit'] ?? null),
            'unit' => filled($payload['unit'] ?? null)
                ? strtolower(trim((string) $payload['unit']))
                : null,
            'category' => filled($payload['category'] ?? null)
                ? Medicine::normalizeCategoryKey((string) $payload['category'])
                : null,
            'base_price' => $payload['base_price'] ?? null,
            'margin_type' => filled($payload['margin_type'] ?? null)
                ? strtolower(trim((string) $payload['margin_type']))
                : 'fixed',
            'margin_value' => filled($payload['margin_value'] ?? null) ? $payload['margin_value'] : 0,
            'is_active' => (bool) $isActive,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'kfa_code' => ['nullable', 'string', 'max:40'],
            'strength_value' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'strength_unit' => ['nullable', 'string', 'max:30'],
            'unit' => ['nullable', 'string', Rule::in(array_keys(Medicine::UNITS))],
            'category' => ['nullable', 'string', Rule::in(array_keys(Medicine::CATEGORIES))],
            'base_price' => ['nullable', 'numeric', 'min:0'],
            'margin_type' => ['nullable', Rule::in(array_keys(Medicine::MARGIN_TYPES))],
            'margin_value' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
