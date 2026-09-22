<?php

namespace App\Http\Controllers\WhatsApp;

use App\Enums\WhatsAppEvent;
use App\Http\Requests\WhatsApp\UpdateReminderRequest;
use App\Models\WhatsAppAutomationRule;
use App\Models\WhatsAppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReminderController extends WhatsAppController
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('whatsapp.automation.view'), 403);

        $tenantId = $this->provision();
        $rules = WhatsAppAutomationRule::query()
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy(fn ($rule) => $rule->event_key->value);

        $reminder = $rules->get(WhatsAppEvent::AppointmentReminder->value);

        return view('whatsapp.reminders', [
            'setting' => WhatsAppSetting::forTenant($tenantId),
            'rules' => $rules,
            'reminder' => $reminder,
            'canManage' => (bool) auth()->user()?->can('whatsapp.automation.manage'),
        ]);
    }

    public function update(UpdateReminderRequest $request): RedirectResponse
    {
        $tenantId = $this->provision();
        $data = $request->validated();

        $this->upsertRule($tenantId, WhatsAppEvent::AppointmentReminder, (bool) ($data['reminder_enabled'] ?? false), [
            'h2' => (bool) ($data['h2'] ?? false),
            'h1' => (bool) ($data['h1'] ?? false),
            'send_time' => $data['send_time'] ?? '09:00',
        ]);

        $this->upsertRule($tenantId, WhatsAppEvent::AppointmentToday, (bool) ($data['today'] ?? false), [
            'send_time' => $data['send_time'] ?? '09:00',
        ]);

        foreach ([
            'confirmation' => WhatsAppEvent::AppointmentConfirmation,
            'rescheduled' => WhatsAppEvent::AppointmentRescheduled,
            'cancelled' => WhatsAppEvent::AppointmentCancelled,
            'missed' => WhatsAppEvent::AppointmentMissed,
            'control' => WhatsAppEvent::ControlReminder,
        ] as $field => $event) {
            $this->upsertRule($tenantId, $event, (bool) ($data[$field] ?? false));
        }

        return back()->with('success', 'Pengaturan pengingat jadwal disimpan.');
    }

    public function activatePreset(): RedirectResponse
    {
        abort_unless(auth()->user()?->can('whatsapp.automation.manage'), 403);

        $tenantId = $this->provision();
        $this->provisioning->enableRecommended($tenantId);

        return back()->with('success', 'Pengingat otomatis diaktifkan.');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function upsertRule(int $tenantId, WhatsAppEvent $event, bool $enabled, array $config = []): void
    {
        $rule = WhatsAppAutomationRule::query()->firstOrNew([
            'tenant_id' => $tenantId,
            'event_key' => $event->value,
        ]);

        $rule->is_enabled = $enabled;
        $rule->config = array_merge($rule->config ?? $this->provisioning->defaultConfig($event), $config);
        $rule->save();
    }
}
