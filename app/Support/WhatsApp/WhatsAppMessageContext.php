<?php

namespace App\Support\WhatsApp;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Visit;
use App\Models\WhatsAppPromotion;
use App\Support\ClinicSettings;
use Illuminate\Support\Carbon;

class WhatsAppMessageContext
{
    /**
     * @param  array<string, mixed>  $clinic
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly array $clinic = [],
        public readonly ?Patient $patient = null,
        public readonly ?Branch $branch = null,
        public readonly ?Visit $visit = null,
        public readonly ?Invoice $invoice = null,
        public readonly ?Payment $payment = null,
        public readonly ?Prescription $prescription = null,
        public readonly ?WhatsAppPromotion $promotion = null,
        public readonly array $extra = [],
    ) {}

    public function clinic(string $key, mixed $default = null): mixed
    {
        return $this->clinic[$key] ?? $default;
    }

    public function doctorName(): string
    {
        return (string) ($this->visit?->doctor?->name
            ?: $this->prescription?->doctor?->name
            ?: '');
    }

    public function doctorSpecialization(): string
    {
        return (string) ($this->visit?->doctor?->doctorProfile?->specialization
            ?: '');
    }

    public function appointmentTime(): string
    {
        if (filled($this->extra['appointment_time'] ?? null)) {
            return (string) $this->extra['appointment_time'];
        }

        $called = $this->visit?->queue?->called_at;

        if ($called) {
            return $called->format('H:i');
        }

        $hours = $this->branch?->opening_hours ?? [];
        $day = strtolower(($this->visit?->visit_date ?? now())->format('l'));
        $start = data_get($hours, $day.'.start') ?: data_get($hours, $day.'.open');

        return filled($start) ? substr((string) $start, 0, 5) : 'sesuai jadwal';
    }

    public function formatDate(mixed $date): string
    {
        if (! $date) {
            return '';
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);
        $months = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];

        return $carbon->format('j').' '.$months[(int) $carbon->format('n')].' '.$carbon->format('Y');
    }

    public function formatMoney(mixed $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function fromVisit(Visit $visit, array $extra = []): self
    {
        $visit->loadMissing(['patient', 'branch', 'doctor.doctorProfile', 'queue', 'room', 'medicalRecord']);

        return new self(
            tenantId: (int) $visit->tenant_id,
            clinic: ClinicSettings::current((int) $visit->tenant_id),
            patient: $visit->patient,
            branch: $visit->branch,
            visit: $visit,
            extra: $extra,
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function fromInvoice(Invoice $invoice, ?Payment $payment = null, array $extra = []): self
    {
        $invoice->loadMissing(['patient', 'branch', 'visit.doctor.doctorProfile', 'visit.queue', 'visit.room']);

        return new self(
            tenantId: (int) $invoice->tenant_id,
            clinic: ClinicSettings::current((int) $invoice->tenant_id),
            patient: $invoice->patient,
            branch: $invoice->branch,
            visit: $invoice->visit,
            invoice: $invoice,
            payment: $payment,
            extra: $extra,
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function fromPrescription(Prescription $prescription, array $extra = []): self
    {
        $prescription->loadMissing(['visit.patient', 'visit.branch', 'visit.doctor.doctorProfile', 'visit.room', 'doctor']);

        $visit = $prescription->visit;

        return new self(
            tenantId: (int) ($visit?->tenant_id ?? 0),
            clinic: ClinicSettings::current((int) ($visit?->tenant_id ?? 0)),
            patient: $visit?->patient,
            branch: $visit?->branch,
            visit: $visit,
            prescription: $prescription,
            extra: $extra,
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function fromPromotion(WhatsAppPromotion $promotion, Patient $patient, ?Branch $branch = null, array $extra = []): self
    {
        $promotion->loadMissing('branch');
        $branch ??= $promotion->branch;

        return new self(
            tenantId: (int) $promotion->tenant_id,
            clinic: ClinicSettings::current((int) $promotion->tenant_id),
            patient: $patient,
            branch: $branch,
            promotion: $promotion,
            extra: $extra,
        );
    }
}
