<?php

namespace App\Support\WhatsApp;

use App\Enums\WhatsAppEvent;
use App\Models\WhatsAppAutomationRule;
use App\Models\WhatsAppGlobalTemplate;
use App\Models\WhatsAppSetting;
use App\Models\WhatsAppTenantTemplate;

class WhatsAppProvisioningService
{
    public function ensureGlobals(): void
    {
        foreach (WhatsAppCatalog::templates() as $index => $template) {
            WhatsAppGlobalTemplate::query()->updateOrCreate(
                ['event_key' => $template['event_key']],
                [
                    'category' => $template['category'],
                    'label' => $template['label'],
                    'default_template_name' => $template['default_template_name'],
                    'default_language' => $template['default_language'],
                    'preview_body' => $template['preview_body'],
                    'default_parameter_map' => $template['default_parameter_map'],
                    'is_recommended' => $template['is_recommended'],
                    'sort_order' => $template['sort_order'] ?? ($index + 1),
                ],
            );
        }
    }

    public function ensureTenant(int $tenantId): void
    {
        $this->ensureGlobals();
        WhatsAppSetting::query()->withoutGlobalScopes()->firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'enabled' => false,
                'api_base_url' => (string) config('services.whatsapp.base_url'),
                'default_language' => 'id',
            ],
        );

        $globals = WhatsAppGlobalTemplate::query()->orderBy('sort_order')->get();

        foreach ($globals as $global) {
            WhatsAppTenantTemplate::query()->withoutGlobalScopes()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'global_template_id' => $global->id,
                ],
                [
                    'event_key' => $global->event_key->value,
                    'whatsapp_template_name' => $global->default_template_name,
                    'language_code' => $global->default_language,
                    'preview_body' => $global->preview_body,
                    'parameter_map' => $global->default_parameter_map,
                    'is_enabled' => $global->is_recommended,
                ],
            );

            WhatsAppAutomationRule::query()->withoutGlobalScopes()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'event_key' => $global->event_key->value,
                ],
                [
                    'is_enabled' => false,
                    'config' => $this->defaultConfig($global->event_key),
                ],
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultConfig(WhatsAppEvent $event): array
    {
        return match ($event) {
            WhatsAppEvent::AppointmentReminder => [
                'h2' => true,
                'h1' => true,
                'send_time' => '09:00',
            ],
            WhatsAppEvent::AppointmentToday => [
                'send_time' => '09:00',
            ],
            default => [],
        };
    }

    /**
     * @param  list<WhatsAppEvent>|null  $events
     */
    public function enableRecommended(int $tenantId, ?array $events = null): void
    {
        $this->ensureTenant($tenantId);
        $events ??= WhatsAppEvent::recommended();

        foreach ($events as $event) {
            WhatsAppAutomationRule::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('event_key', $event->value)
                ->update(['is_enabled' => true]);

            WhatsAppTenantTemplate::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('event_key', $event->value)
                ->update(['is_enabled' => true]);
        }
    }
}
