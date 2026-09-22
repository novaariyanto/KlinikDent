<?php

namespace App\Support\WhatsApp;

use App\Enums\VisitStatus;
use App\Enums\WhatsAppEvent;
use App\Enums\WhatsAppMessageStatus;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Visit;
use App\Models\WhatsAppAutomationRule;
use App\Models\WhatsAppMessageLog;
use App\Models\WhatsAppPromotion;
use App\Models\WhatsAppSetting;
use App\Models\WhatsAppTenantTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class WhatsAppAutomationService
{
    public function __construct(
        protected WhatsAppTemplateService $templates,
        protected WhatsAppProvisioningService $provisioning,
        protected WhatsAppParameterResolver $resolver,
    ) {}

    public function queueVisit(WhatsAppEvent $event, Visit $visit, ?string $scheduledType = null): ?WhatsAppMessageLog
    {
        return $this->queue($event, $visit, WhatsAppMessageContext::fromVisit($visit), $scheduledType);
    }

    public function queueInvoice(WhatsAppEvent $event, Invoice $invoice, ?Payment $payment = null, ?string $scheduledType = null): ?WhatsAppMessageLog
    {
        if ($event === WhatsAppEvent::PaymentReminder && bccomp($invoice->remainingAmount(), '0', 2) <= 0) {
            return null;
        }

        return $this->queue($event, $payment ?? $invoice, WhatsAppMessageContext::fromInvoice($invoice, $payment), $scheduledType);
    }

    public function queuePrescription(Prescription $prescription): ?WhatsAppMessageLog
    {
        if (! $prescription->isFulfilled()) {
            return null;
        }

        return $this->queue(
            WhatsAppEvent::PrescriptionReady,
            $prescription,
            WhatsAppMessageContext::fromPrescription($prescription),
        );
    }

    public function queuePromotion(WhatsAppPromotion $promotion, Patient $patient): ?WhatsAppMessageLog
    {
        if (! $patient->marketing_consent) {
            return $this->reject(
                WhatsAppEvent::PromotionGeneral,
                $promotion,
                WhatsAppMessageContext::fromPromotion($promotion, $patient),
                'marketing_consent',
                'Pasien tidak memiliki persetujuan komunikasi marketing.',
                'promotion:'.$promotion->id.':patient:'.$patient->id,
            );
        }

        return $this->queue(
            WhatsAppEvent::PromotionGeneral,
            $promotion,
            WhatsAppMessageContext::fromPromotion($promotion, $patient, $promotion->branch),
            null,
            'promotion:'.$promotion->id.':patient:'.$patient->id,
        );
    }

    public function queueBirthday(Patient $patient, ?WhatsAppPromotion $promotion = null): ?WhatsAppMessageLog
    {
        $context = $promotion
            ? WhatsAppMessageContext::fromPromotion($promotion, $patient)
            : new WhatsAppMessageContext(
                tenantId: (int) $patient->tenant_id,
                clinic: \App\Support\ClinicSettings::current((int) $patient->tenant_id),
                patient: $patient,
                extra: [
                    'promotion_title' => 'Selamat ulang tahun',
                    'promotion_message' => 'Selamat ulang tahun. Nikmati penawaran spesial dari klinik kami.',
                ],
            );

        return $this->queue(
            WhatsAppEvent::BirthdayPromotion,
            $patient,
            $context,
            'birthday:'.now()->format('Y'),
        );
    }

    public function syncControlDates(Visit $visit): void
    {
        $record = $visit->medicalRecord;

        if (! $record) {
            return;
        }

        $items = $record->plan_instructions['items'] ?? [];
        $visitDate = $visit->visit_date ? Carbon::parse($visit->visit_date) : now();
        $controlDate = $record->control_date;
        $followupDate = $record->followup_date;

        if (in_array('kontrol_1_minggu', $items, true)) {
            $controlDate = $visitDate->copy()->addWeek()->toDateString();
        } elseif (in_array('kontrol_2_minggu', $items, true)) {
            $controlDate = $visitDate->copy()->addWeeks(2)->toDateString();
        }

        if ($controlDate || $followupDate) {
            $record->update([
                'control_date' => $controlDate,
                'followup_date' => $followupDate,
            ]);
        }
    }

    public function queue(
        WhatsAppEvent $event,
        Model $related,
        WhatsAppMessageContext $context,
        ?string $scheduledType = null,
        ?string $idempotency = null,
    ): ?WhatsAppMessageLog {
        $tenantId = $context->tenantId ?: (int) ($related->tenant_id ?? 0);

        if ($tenantId < 1) {
            return null;
        }

        $setting = WhatsAppSetting::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->first();

        if (! $setting?->enabled) {
            return null;
        }

        $rule = WhatsAppAutomationRule::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('event_key', $event->value)
            ->first();

        if (! $rule?->is_enabled) {
            return null;
        }

        $template = WhatsAppTenantTemplate::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('event_key', $event->value)
            ->first();

        $idempotency ??= $this->idempotencyKey($event, $related, $scheduledType);

        if ($this->existingSuccessful($tenantId, $idempotency)) {
            return null;
        }

        if (! $template?->is_enabled) {
            return $this->reject($event, $related, $context, 'template_disabled', 'Template tidak aktif.', $idempotency, $scheduledType);
        }

        if (! $template->isRemoteApproved()) {
            return $this->reject($event, $related, $context, 'template_not_approved', 'Template WhatsCRM belum approved.', $idempotency, $scheduledType);
        }

        if (! $setting->isReady()) {
            return $this->reject($event, $related, $context, 'missing_credentials', 'API credential WhatsApp belum lengkap.', $idempotency, $scheduledType);
        }

        if ($event->requiresMarketingConsent() && ! $context->patient?->marketing_consent) {
            return $this->reject($event, $related, $context, 'marketing_consent', 'Pasien tidak memiliki persetujuan komunikasi marketing.', $idempotency, $scheduledType);
        }

        if ($event === WhatsAppEvent::AppointmentCancelled && $context->visit?->status !== VisitStatus::Cancelled) {
            return $this->reject($event, $related, $context, 'event_invalid', 'Kunjungan tidak berstatus batal.', $idempotency, $scheduledType);
        }

        if ($event === WhatsAppEvent::PaymentReminder && $context->invoice && bccomp($context->invoice->remainingAmount(), '0', 2) <= 0) {
            return null;
        }

        if (in_array($event, [
            WhatsAppEvent::AppointmentCreated,
            WhatsAppEvent::AppointmentConfirmation,
            WhatsAppEvent::AppointmentReminder,
            WhatsAppEvent::AppointmentToday,
            WhatsAppEvent::AppointmentRescheduled,
        ], true) && $context->visit && ! $context->visit->status?->isOpen()) {
            return null;
        }

        if (in_array($event, [WhatsAppEvent::ControlReminder, WhatsAppEvent::FollowupReminder], true)) {
            $date = $event === WhatsAppEvent::ControlReminder
                ? ($context->visit?->medicalRecord?->control_date ?? $context->extra['control_date'] ?? null)
                : ($context->visit?->medicalRecord?->followup_date ?? $context->extra['followup_date'] ?? null);

            if (! $date) {
                return null;
            }
        }

        $phone = WhatsAppPhone::normalize($context->patient?->phone);

        if (! $phone) {
            return $this->reject($event, $related, $context, 'invalid_phone', 'Nomor WhatsApp pasien tidak valid.', $idempotency, $scheduledType);
        }

        $mapping = $template->resolvedParameterMap();

        try {
            $this->templates->validateParameters($mapping);
        } catch (WhatsAppException $exception) {
            return $this->reject($event, $related, $context, $exception->errorCode, $exception->getMessage(), $idempotency, $scheduledType);
        }

        $resolved = $this->resolver->resolveAll($mapping, $context);
        $payload = $this->templates->buildPayload(
            $template,
            $phone,
            (string) ($context->patient?->name ?? ''),
            $resolved,
        );

        $log = $this->firstOrCreateLog($tenantId, $idempotency, [
            'branch_id' => $context->branch?->id ?? $context->visit?->branch_id,
            'patient_id' => $context->patient?->id,
            'event_key' => $event->value,
            'category' => $event->category()->value,
            'template_name' => $template->whatsapp_template_name,
            'phone_number' => $phone,
            'to_name' => $context->patient?->name,
            'body_params' => $resolved,
            'status' => WhatsAppMessageStatus::Queued->value,
            'related_type' => $related->getMorphClass(),
            'related_id' => $related->getKey(),
            'scheduled_type' => $scheduledType,
            'request_payload' => $payload,
        ]);

        if (! $log || $log->status->isSuccess() || $log->status === WhatsAppMessageStatus::Processing) {
            return $log;
        }

        if ($log->status === WhatsAppMessageStatus::Failed && $this->isPermanentFailure($log->error_code)) {
            return $log;
        }

        $this->templates->dispatch($log);

        return $log->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function firstOrCreateLog(int $tenantId, string $idempotency, array $attributes): ?WhatsAppMessageLog
    {
        $existing = WhatsAppMessageLog::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('idempotency_key', $idempotency)
            ->first();

        if ($existing) {
            if ($existing->status->isSuccess()
                || $existing->status === WhatsAppMessageStatus::Processing
                || $existing->status === WhatsAppMessageStatus::Queued) {
                return $existing;
            }

            if ($existing->status === WhatsAppMessageStatus::Failed && $this->isPermanentFailure($existing->error_code)) {
                return $existing;
            }

            $existing->fill($attributes);
            $existing->status = WhatsAppMessageStatus::Queued;
            $existing->error_code = null;
            $existing->error_message = null;
            $existing->failed_at = null;
            $existing->save();

            return $existing;
        }

        try {
            return WhatsAppMessageLog::query()->create(array_merge($attributes, [
                'tenant_id' => $tenantId,
                'idempotency_key' => $idempotency,
            ]));
        } catch (\Throwable) {
            return WhatsAppMessageLog::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('idempotency_key', $idempotency)
                ->first();
        }
    }

    protected function existingSuccessful(int $tenantId, string $idempotency): bool
    {
        return WhatsAppMessageLog::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('idempotency_key', $idempotency)
            ->whereIn('status', [
                WhatsAppMessageStatus::Queued->value,
                WhatsAppMessageStatus::Processing->value,
                WhatsAppMessageStatus::Sent->value,
                WhatsAppMessageStatus::Delivered->value,
                WhatsAppMessageStatus::Read->value,
            ])
            ->exists();
    }

    protected function reject(
        WhatsAppEvent $event,
        Model $related,
        WhatsAppMessageContext $context,
        string $code,
        string $message,
        string $idempotency,
        ?string $scheduledType = null,
    ): ?WhatsAppMessageLog {
        if ($this->existingSuccessful($context->tenantId, $idempotency)) {
            return null;
        }

        $existing = WhatsAppMessageLog::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $context->tenantId)
            ->where('idempotency_key', $idempotency)
            ->first();

        if ($existing) {
            return $existing;
        }

        return WhatsAppMessageLog::query()->create([
            'tenant_id' => $context->tenantId,
            'branch_id' => $context->branch?->id ?? $context->visit?->branch_id,
            'patient_id' => $context->patient?->id,
            'event_key' => $event->value,
            'category' => $event->category()->value,
            'template_name' => $event->value,
            'phone_number' => WhatsAppPhone::normalize($context->patient?->phone),
            'to_name' => $context->patient?->name,
            'status' => WhatsAppMessageStatus::Failed,
            'idempotency_key' => $idempotency,
            'related_type' => $related->getMorphClass(),
            'related_id' => $related->getKey(),
            'scheduled_type' => $scheduledType,
            'error_code' => $code,
            'error_message' => $message,
            'failed_at' => now(),
        ]);
    }

    public function idempotencyKey(WhatsAppEvent $event, Model $related, ?string $scheduledType = null): string
    {
        $parts = [
            $event->value,
            $related->getMorphClass(),
            (string) $related->getKey(),
        ];

        if ($scheduledType) {
            $parts[] = $scheduledType;
        }

        return implode(':', $parts);
    }

    protected function isPermanentFailure(?string $code): bool
    {
        return in_array($code, ['event_invalid', 'invalid_parameters', 'literal_placeholder', 'template_not_approved'], true);
    }
}
