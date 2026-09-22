<?php

namespace App\Support\WhatsApp;

class WhatsAppParameterResolver
{
    /**
     * @param  list<array<string, mixed>>  $mapping
     * @return list<string>
     */
    public function resolveAll(array $mapping, WhatsAppMessageContext $context): array
    {
        $values = [];

        foreach ($mapping as $row) {
            $values[] = $this->resolve((string) ($row['source'] ?? ''), $context);
        }

        return $values;
    }

    public function resolve(string $source, WhatsAppMessageContext $context): string
    {
        $source = trim($source);

        if ($source === '') {
            return '';
        }

        $value = match ($source) {
            'patient.name' => $context->patient?->name,
            'patient.phone' => $context->patient?->phone,
            'clinic.name' => $context->clinic('clinic_name'),
            'clinic.phone' => $context->clinic('phone'),
            'branch.name' => $context->branch?->name,
            'branch.address' => $context->branch?->address,
            'appointment.date' => $context->formatDate($context->visit?->visit_date),
            'appointment.time' => $context->appointmentTime(),
            'appointment.status' => $context->visit?->status?->label(),
            'doctor.name' => $context->doctorName(),
            'doctor.specialization' => $context->doctorSpecialization(),
            'room.name' => $context->visit?->room?->name,
            'invoice.number' => $context->invoice?->number,
            'invoice.total' => $context->invoice ? $context->formatMoney($context->invoice->total_amount) : null,
            'invoice.paid' => $context->invoice ? $context->formatMoney($context->invoice->paid_amount) : null,
            'invoice.remaining' => $context->invoice ? $context->formatMoney($context->invoice->remainingAmount()) : null,
            'invoice.date' => $context->formatDate($context->invoice?->created_at),
            'payment.date' => $context->formatDate($context->payment?->paid_at),
            'payment.amount' => $context->payment ? $context->formatMoney($context->payment->amount) : null,
            'prescription.date' => $context->formatDate($context->prescription?->updated_at ?? $context->prescription?->created_at),
            'treatment.control_date' => $context->formatDate($context->visit?->medicalRecord?->control_date ?? ($context->extra['control_date'] ?? null)),
            'treatment.followup_date' => $context->formatDate($context->visit?->medicalRecord?->followup_date ?? ($context->extra['followup_date'] ?? null)),
            'promotion.title' => $context->promotion?->title ?? ($context->extra['promotion_title'] ?? null),
            'promotion.message' => $context->promotion?->message ?? ($context->extra['promotion_message'] ?? null),
            'promotion.start_date' => $context->formatDate($context->promotion?->starts_at),
            'promotion.end_date' => $context->formatDate($context->promotion?->ends_at),
            default => $this->fromExtra($source, $context),
        };

        $resolved = trim((string) $value);

        return $resolved !== '' ? $resolved : '-';
    }

    protected function fromExtra(string $source, WhatsAppMessageContext $context): mixed
    {
        return $context->extra[$source] ?? data_get($context->extra, $source);
    }
}
