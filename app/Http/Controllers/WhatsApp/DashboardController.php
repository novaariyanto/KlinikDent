<?php

namespace App\Http\Controllers\WhatsApp;

use App\Enums\WhatsAppEvent;
use App\Enums\WhatsAppMessageStatus;
use App\Models\WhatsAppAutomationRule;
use App\Models\WhatsAppMessageLog;
use App\Models\WhatsAppSetting;
use App\Models\WhatsAppTenantTemplate;
use App\Support\WhatsApp\WhatsAppBalanceService;
use App\Support\WhatsApp\WhatsAppException;
use Illuminate\View\View;

class DashboardController extends WhatsAppController
{
    public function index(WhatsAppBalanceService $balance): View
    {
        abort_unless(auth()->user()?->can('whatsapp.view'), 403);

        $tenantId = $this->provision();
        $setting = WhatsAppSetting::forTenant($tenantId);
        $account = null;
        $accountError = null;

        if ($setting->isReady()) {
            try {
                $account = $balance->account($tenantId);
            } catch (WhatsAppException $exception) {
                $accountError = $exception->getMessage();
            }
        }

        $today = WhatsAppMessageLog::query()->today();
        $rules = WhatsAppAutomationRule::query()
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy(fn ($rule) => $rule->event_key->value);

        return view('whatsapp.dashboard', [
            'setting' => $setting,
            'account' => $account,
            'accountError' => $accountError,
            'todayTotal' => (clone $today)->count(),
            'todaySent' => (clone $today)->whereIn('status', [
                WhatsAppMessageStatus::Sent->value,
                WhatsAppMessageStatus::Delivered->value,
                WhatsAppMessageStatus::Read->value,
            ])->count(),
            'todayFailed' => (clone $today)->where('status', WhatsAppMessageStatus::Failed->value)->count(),
            'activeRules' => $rules->where('is_enabled', true)->count(),
            'activeTemplates' => WhatsAppTenantTemplate::query()->where('is_enabled', true)->count(),
            'groups' => [
                'Pengingat Jadwal' => $this->groupActive($rules, [
                    WhatsAppEvent::AppointmentReminder,
                    WhatsAppEvent::AppointmentToday,
                ]),
                'Konfirmasi Appointment' => $this->groupActive($rules, [WhatsAppEvent::AppointmentConfirmation]),
                'Follow-up' => $this->groupActive($rules, [
                    WhatsAppEvent::ControlReminder,
                    WhatsAppEvent::FollowupReminder,
                ]),
                'Promosi' => $this->groupActive($rules, [
                    WhatsAppEvent::PromotionGeneral,
                    WhatsAppEvent::BirthdayPromotion,
                ]),
            ],
            'recommended' => WhatsAppEvent::recommended(),
            'canManage' => (bool) auth()->user()?->can('whatsapp.automation.manage'),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<string, WhatsAppAutomationRule>  $rules
     * @param  list<WhatsAppEvent>  $events
     */
    protected function groupActive($rules, array $events): bool
    {
        foreach ($events as $event) {
            if ($rules->get($event->value)?->is_enabled) {
                return true;
            }
        }

        return false;
    }
}
