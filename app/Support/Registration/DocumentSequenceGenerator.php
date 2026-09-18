<?php

namespace App\Support\Registration;

use App\Models\Branch;
use App\Models\DocumentSequence;
use App\Models\Tenant;
use App\Support\ClinicSettings;
use Illuminate\Database\QueryException;

class DocumentSequenceGenerator
{
    public const TYPE_MEDICAL_RECORD = 'medical_record';

    public const TYPE_QUEUE = 'queue';

    public const TYPE_INVOICE = 'invoice';

    public function nextMedicalRecord(Tenant $tenant, Branch $branch): string
    {
        $year = now()->year;
        $number = $this->next((int) $tenant->id, (int) $branch->id, self::TYPE_MEDICAL_RECORD, (string) $year);
        $tenantCode = ClinicSettings::rmPrefix($tenant);
        $branchCode = str_pad((string) $branch->id, 2, '0', STR_PAD_LEFT);

        return sprintf('%s-%s-%s-%s', $tenantCode, $branchCode, $year, str_pad((string) $number, 6, '0', STR_PAD_LEFT));
    }

    public function nextQueueNumber(Branch $branch, ?string $date = null): int
    {
        $period = $date ?: now()->toDateString();

        return $this->next((int) $branch->tenant_id, (int) $branch->id, self::TYPE_QUEUE, $period);
    }

    public function nextInvoice(Tenant $tenant, Branch $branch): string
    {
        $year = now()->year;
        $number = $this->next((int) $tenant->id, (int) $branch->id, self::TYPE_INVOICE, (string) $year);
        $branchCode = str_pad((string) $branch->id, 2, '0', STR_PAD_LEFT);

        return sprintf('INV-%s-%s-%s', $branchCode, $year, str_pad((string) $number, 6, '0', STR_PAD_LEFT));
    }

    public function next(int $tenantId, int $branchId, string $type, string $period): int
    {
        return retry(5, function () use ($tenantId, $branchId, $type, $period) {
            try {
                $sequence = DocumentSequence::query()
                    ->where('tenant_id', $tenantId)
                    ->where('branch_id', $branchId)
                    ->where('type', $type)
                    ->where('period', $period)
                    ->lockForUpdate()
                    ->first();

                if (! $sequence) {
                    $sequence = DocumentSequence::query()->create([
                        'tenant_id' => $tenantId,
                        'branch_id' => $branchId,
                        'type' => $type,
                        'period' => $period,
                        'last_number' => 0,
                    ]);
                }

                $sequence->last_number++;
                $sequence->save();

                return (int) $sequence->last_number;
            } catch (QueryException $exception) {
                if (! $this->isUniqueConflict($exception)) {
                    throw $exception;
                }

                throw $exception;
            }
        });
    }

    protected function isUniqueConflict(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();
        $message = $exception->getMessage();

        return $code === '23000'
            || str_contains($message, 'UNIQUE constraint failed')
            || str_contains($message, 'Duplicate entry');
    }
}
