<?php

namespace App\Jobs;

use App\Models\DentalLabOrder;
use App\Models\Invoice;
use App\Models\MedicalRecord;
use App\Models\MedicineStock;
use App\Models\OdontogramTooth;
use App\Models\Patient;
use App\Models\PracticeArchive;
use App\Models\Prescription;
use App\Models\Visit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class GeneratePracticeArchive implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $archiveId) {}

    public function handle(): void
    {
        $archive = PracticeArchive::withoutGlobalScopes()->find($this->archiveId);

        if (! $archive) {
            return;
        }

        $tenantId = (int) $archive->tenant_id;
        $relative = 'archives/'.$tenantId.'/praktik-'.$archive->id.'.zip';
        $absolute = Storage::disk('local')->path($relative);
        $directory = dirname($absolute);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $archive->update(['status' => 'failed', 'error' => 'Folder arsip tidak dapat dibuat.']);

            return;
        }

        $zip = new ZipArchive;

        if ($zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $archive->update(['status' => 'failed', 'error' => 'Berkas ZIP tidak dapat dibuat.']);

            return;
        }

        try {
            $this->addCsv($zip, 'pasien.csv', ['id', 'nomor_rm', 'nama', 'nik', 'lahir', 'telepon'], Patient::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('id')->get()->map(fn (Patient $patient) => [
                $patient->id,
                $patient->medical_record_number,
                $patient->name,
                $patient->nik,
                $patient->dob?->toDateString(),
                $patient->phone,
            ]));

            $this->addCsv($zip, 'kunjungan.csv', ['id', 'pasien_id', 'tanggal', 'dokter_id', 'status'], Visit::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('id')->get()->map(fn (Visit $visit) => [
                $visit->id,
                $visit->patient_id,
                $visit->visit_date?->toDateString(),
                $visit->doctor_id,
                $visit->status?->value,
            ]));

            $visitIds = Visit::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('id');

            $this->addCsv($zip, 'rekam_medis.csv', ['kunjungan_id', 'keluhan', 'anamnesis', 'catatan', 'final'], MedicalRecord::query()->whereIn('visit_id', $visitIds)->orderBy('id')->get()->map(fn (MedicalRecord $record) => [
                $record->visit_id,
                $record->chief_complaint,
                $record->anamnesis,
                $record->clinical_notes,
                $record->finalized_at?->toDateTimeString(),
            ]));

            $patientIds = Patient::withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('id');

            $this->addCsv($zip, 'odontogram.csv', ['pasien_id', 'gigi', 'status', 'catatan'], OdontogramTooth::query()->whereIn('patient_id', $patientIds)->orderBy('id')->get()->map(fn (OdontogramTooth $tooth) => [
                $tooth->patient_id,
                $tooth->tooth_number,
                $tooth->status?->value ?? $tooth->status,
                $tooth->notes,
            ]));

            $this->addCsv($zip, 'resep.csv', ['id', 'kunjungan_id', 'status'], Prescription::query()->whereIn('visit_id', $visitIds)->orderBy('id')->get()->map(fn (Prescription $prescription) => [
                $prescription->id,
                $prescription->visit_id,
                $prescription->status?->value,
            ]));

            $this->addCsv($zip, 'tagihan.csv', ['nomor', 'pasien_id', 'total', 'dibayar', 'status'], Invoice::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('id')->get()->map(fn (Invoice $invoice) => [
                $invoice->number,
                $invoice->patient_id,
                $invoice->total_amount,
                $invoice->paid_amount,
                $invoice->status?->value,
            ]));

            $this->addCsv($zip, 'stok.csv', ['obat_id', 'batch', 'kedaluwarsa', 'qty'], MedicineStock::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('id')->get()->map(fn (MedicineStock $stock) => [
                $stock->medicine_id,
                $stock->batch_number,
                $stock->expired_date?->toDateString(),
                $stock->quantity,
            ]));

            $this->addCsv($zip, 'lab_gigi.csv', ['kunjungan_id', 'jenis', 'status'], DentalLabOrder::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('id')->get()->map(fn (DentalLabOrder $order) => [
                $order->visit_id,
                $order->work_type,
                $order->status?->value,
            ]));

            $zip->close();
        } catch (\Throwable $exception) {
            $zip->close();
            $archive->update(['status' => 'failed', 'error' => $exception->getMessage()]);

            return;
        }

        $archive->update([
            'status' => 'ready',
            'path' => $relative,
            'expires_at' => now()->addDay(),
            'error' => null,
        ]);
    }

    /**
     * @param  list<string>  $header
     * @param  iterable<int, list<mixed>>  $rows
     */
    protected function addCsv(ZipArchive $zip, string $name, array $header, iterable $rows): void
    {
        $handle = fopen('php://temp', 'w+');

        if ($handle === false) {
            throw new RuntimeException('CSV tidak dapat dibuat.');
        }

        fputcsv($handle, $header);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $zip->addFromString($name, (string) stream_get_contents($handle));
        fclose($handle);
    }
}
