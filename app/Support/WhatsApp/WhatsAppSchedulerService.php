<?php

namespace App\Support\WhatsApp;

use App\Enums\InvoiceStatus;
use App\Enums\VisitStatus;
use App\Enums\WhatsAppEvent;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\WhatsAppAutomationRule;
use App\Models\WhatsAppPromotion;
use App\Models\WhatsAppSetting;
use Illuminate\Support\Carbon;

class WhatsAppSchedulerService
{
    public function __construct(protected WhatsAppAutomationService $automation) {}

    public function dispatchDue(): int
    {
        $count = 0;
        $count += $this->dispatchVisitReminders();
        $count += $this->dispatchMissedVisits();
        $count += $this->dispatchControlReminders();
        $count += $this->dispatchPaymentReminders();
        $count += $this->dispatchBirthdayPromotions();
        $count += $this->dispatchPromotions();

        return $count;
    }

    public function dispatchVisitReminders(): int
    {
        $count = 0;
        $now = now();

        $settings = WhatsAppSetting::query()
            ->withoutGlobalScopes()
            ->where('enabled', true)
            ->get()
            ->keyBy('tenant_id');

        if ($settings->isEmpty()) {
            return 0;
        }

        $tenantIds = $settings->keys()->all();

        $rules = WhatsAppAutomationRule::query()
            ->withoutGlobalScopes()
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('event_key', [
                WhatsAppEvent::AppointmentReminder->value,
                WhatsAppEvent::AppointmentToday->value,
            ])
            ->where('is_enabled', true)
            ->get()
            ->groupBy('tenant_id');

        foreach ($rules as $tenantId => $tenantRules) {
            $reminder = $tenantRules->firstWhere('event_key', WhatsAppEvent::AppointmentReminder);
            $todayRule = $tenantRules->firstWhere('event_key', WhatsAppEvent::AppointmentToday);
            $sendTime = $reminder?->configValue('send_time') ?: $todayRule?->configValue('send_time') ?: '09:00';

            if (! $this->sendWindowReached($now, (string) $sendTime)) {
                continue;
            }

            $windows = [];

            if ($reminder?->configValue('h2')) {
                $windows[] = ['event' => WhatsAppEvent::AppointmentReminder, 'type' => 'h2', 'date' => $now->copy()->addDays(2)->toDateString()];
            }

            if ($reminder?->configValue('h1')) {
                $windows[] = ['event' => WhatsAppEvent::AppointmentReminder, 'type' => 'h1', 'date' => $now->copy()->addDay()->toDateString()];
            }

            if ($todayRule) {
                $windows[] = ['event' => WhatsAppEvent::AppointmentToday, 'type' => 'today', 'date' => $now->toDateString()];
            }

            foreach ($windows as $window) {
                $visits = Visit::query()
                    ->withoutGlobalScopes()
                    ->with(['patient', 'branch', 'doctor.doctorProfile', 'queue', 'room'])
                    ->where('tenant_id', $tenantId)
                    ->whereDate('visit_date', $window['date'])
                    ->whereIn('status', [VisitStatus::Waiting->value, VisitStatus::InService->value])
                    ->limit(200)
                    ->get();

                foreach ($visits as $visit) {
                    $log = $this->automation->queueVisit($window['event'], $visit, $window['type']);
                    $count += $log ? 1 : 0;
                }
            }
        }

        return $count;
    }

    public function dispatchMissedVisits(): int
    {
        $count = 0;
        $yesterday = now()->subDay()->toDateString();

        $tenantIds = WhatsAppAutomationRule::query()
            ->withoutGlobalScopes()
            ->where('event_key', WhatsAppEvent::AppointmentMissed->value)
            ->where('is_enabled', true)
            ->pluck('tenant_id');

        if ($tenantIds->isEmpty()) {
            return 0;
        }

        $visits = Visit::query()
            ->withoutGlobalScopes()
            ->with(['patient', 'branch', 'doctor.doctorProfile', 'queue', 'room'])
            ->whereIn('tenant_id', $tenantIds)
            ->whereDate('visit_date', $yesterday)
            ->where('status', VisitStatus::Waiting->value)
            ->limit(200)
            ->get();

        foreach ($visits as $visit) {
            $log = $this->automation->queueVisit(WhatsAppEvent::AppointmentMissed, $visit, 'missed');
            $count += $log ? 1 : 0;
        }

        return $count;
    }

    public function dispatchControlReminders(): int
    {
        $count = 0;
        $today = now()->toDateString();

        $tenantIds = WhatsAppAutomationRule::query()
            ->withoutGlobalScopes()
            ->whereIn('event_key', [
                WhatsAppEvent::ControlReminder->value,
                WhatsAppEvent::FollowupReminder->value,
            ])
            ->where('is_enabled', true)
            ->pluck('tenant_id')
            ->unique();

        if ($tenantIds->isEmpty()) {
            return 0;
        }

        $visits = Visit::query()
            ->withoutGlobalScopes()
            ->with(['patient', 'branch', 'doctor.doctorProfile', 'queue', 'room', 'medicalRecord'])
            ->whereIn('tenant_id', $tenantIds)
            ->whereHas('medicalRecord', function ($query) use ($today) {
                $query->withoutGlobalScopes()
                    ->whereDate('control_date', $today)
                    ->orWhereDate('followup_date', $today);
            })
            ->limit(200)
            ->get();

        foreach ($visits as $visit) {
            $record = $visit->medicalRecord;

            if ($record?->control_date?->toDateString() === $today) {
                $count += $this->automation->queueVisit(WhatsAppEvent::ControlReminder, $visit, 'control') ? 1 : 0;
            }

            if ($record?->followup_date?->toDateString() === $today) {
                $count += $this->automation->queueVisit(WhatsAppEvent::FollowupReminder, $visit, 'followup') ? 1 : 0;
            }
        }

        return $count;
    }

    public function dispatchPaymentReminders(): int
    {
        $count = 0;
        $tenantIds = WhatsAppAutomationRule::query()
            ->withoutGlobalScopes()
            ->where('event_key', WhatsAppEvent::PaymentReminder->value)
            ->where('is_enabled', true)
            ->pluck('tenant_id');

        if ($tenantIds->isEmpty()) {
            return 0;
        }

        $invoices = Invoice::query()
            ->withoutGlobalScopes()
            ->with(['patient', 'branch', 'visit'])
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('status', [InvoiceStatus::Unpaid->value, InvoiceStatus::Partial->value])
            ->whereDate('created_at', '<=', now()->subDay()->toDateString())
            ->limit(200)
            ->get();

        foreach ($invoices as $invoice) {
            if (bccomp($invoice->remainingAmount(), '0', 2) <= 0) {
                continue;
            }

            $log = $this->automation->queueInvoice(
                WhatsAppEvent::PaymentReminder,
                $invoice,
                null,
                'outstanding:'.now()->toDateString(),
            );
            $count += $log ? 1 : 0;
        }

        return $count;
    }

    public function dispatchBirthdayPromotions(): int
    {
        $count = 0;
        $tenantIds = WhatsAppAutomationRule::query()
            ->withoutGlobalScopes()
            ->where('event_key', WhatsAppEvent::BirthdayPromotion->value)
            ->where('is_enabled', true)
            ->pluck('tenant_id');

        if ($tenantIds->isEmpty()) {
            return 0;
        }

        $month = now()->month;
        $day = now()->day;

        $patients = Patient::query()
            ->withoutGlobalScopes()
            ->whereIn('tenant_id', $tenantIds)
            ->where('marketing_consent', true)
            ->whereNotNull('dob')
            ->whereNotNull('phone')
            ->whereMonth('dob', $month)
            ->whereDay('dob', $day)
            ->limit(200)
            ->get();

        foreach ($patients as $patient) {
            $count += $this->automation->queueBirthday($patient) ? 1 : 0;
        }

        return $count;
    }

    public function dispatchPromotions(): int
    {
        $count = 0;
        $today = now()->toDateString();

        $promotions = WhatsAppPromotion::query()
            ->withoutGlobalScopes()
            ->with('branch')
            ->where('is_enabled', true)
            ->whereNull('dispatched_at')
            ->whereDate('starts_at', '<=', $today)
            ->whereDate('ends_at', '>=', $today)
            ->limit(20)
            ->get();

        foreach ($promotions as $promotion) {
            $count += $this->sendPromotion($promotion);
            $promotion->update(['dispatched_at' => now()]);
        }

        return $count;
    }

    public function sendPromotion(WhatsAppPromotion $promotion): int
    {
        $count = 0;

        $query = Patient::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $promotion->tenant_id)
            ->where('marketing_consent', true)
            ->whereNotNull('phone');

        if ($promotion->branch_id) {
            $query->whereHas('visits', function ($visits) use ($promotion) {
                $visits->withoutGlobalScopes()->where('branch_id', $promotion->branch_id);
            });
        }

        $query->orderBy('id')->chunkById(100, function ($patients) use ($promotion, &$count) {
            foreach ($patients as $patient) {
                $count += $this->automation->queuePromotion($promotion, $patient) ? 1 : 0;
            }
        });

        return $count;
    }

    protected function sendWindowReached(Carbon $now, string $sendTime): bool
    {
        if (! preg_match('/^\d{2}:\d{2}$/', $sendTime)) {
            return true;
        }

        return $now->format('H:i') >= $sendTime;
    }
}
