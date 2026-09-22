<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\RoleName;
use App\Enums\VisitStatus;
use App\Enums\WhatsAppEvent;
use App\Enums\WhatsAppMessageStatus;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Menu;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visit;
use App\Models\WhatsAppAutomationRule;
use App\Models\WhatsAppMessageLog;
use App\Models\WhatsAppPromotion;
use App\Models\WhatsAppSetting;
use App\Models\WhatsAppTenantTemplate;
use App\Support\WhatsApp\WhatsAppAutomationService;
use App\Support\WhatsApp\WhatsAppException;
use App\Support\WhatsApp\WhatsAppMessageContext;
use App\Support\WhatsApp\WhatsAppParameterResolver;
use App\Support\WhatsApp\WhatsAppProvisioningService;
use App\Support\WhatsApp\WhatsAppTemplateService;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WhatsAppTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WhatsAppAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Branch $branch;

    protected User $owner;

    protected Patient $patient;

    protected Payer $payer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            MenuSeeder::class,
            WhatsAppTemplateSeeder::class,
        ]);
        Menu::clearCache();

        $this->tenant = Tenant::factory()->create(['name' => 'KlinikDent']);
        $this->branch = Branch::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Cabang Pati']);
        $this->owner = $this->tenantUser(RoleName::Owner);
        $this->payer = Payer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->patient = Patient::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Budi',
            'phone' => '081234567890',
            'marketing_consent' => false,
        ]);
    }

    public function test_owner_can_open_whatsapp_module_and_see_templates(): void
    {
        $this->actingAs($this->owner)
            ->get(route('whatsapp.dashboard'))
            ->assertOk()
            ->assertSee('WhatsApp Otomatis');

        $this->actingAs($this->owner)
            ->get(route('whatsapp.templates.index'))
            ->assertOk()
            ->assertSee('appointment_created')
            ->assertSee('birthday_promotion');

        $this->assertSame(16, WhatsAppTenantTemplate::query()->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_owner_can_save_masked_api_key_and_custom_template_name(): void
    {
        $this->actingAs($this->owner)
            ->put(route('whatsapp.settings.update'), [
                'enabled' => '1',
                'api_base_url' => 'https://whatscrm.id',
                'api_key' => 'secret-key-7890',
                'phone_number' => '628111111111',
                'business_name' => 'KlinikDent',
                'default_language' => 'id',
            ])
            ->assertRedirect();

        $setting = WhatsAppSetting::query()->where('tenant_id', $this->tenant->id)->first();
        $this->assertTrue($setting->enabled);
        $this->assertSame('secret-key-7890', $setting->api_key);
        $this->assertStringContainsString('7890', $setting->maskedApiKey());
        $this->assertStringNotContainsString('secret-key', $setting->maskedApiKey());

        $this->actingAs($this->owner)
            ->get(route('whatsapp.settings'))
            ->assertOk()
            ->assertDontSee('secret-key-7890')
            ->assertSee('7890');

        app(WhatsAppProvisioningService::class)->ensureTenant($this->tenant->id);
        $template = WhatsAppTenantTemplate::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('event_key', WhatsAppEvent::AppointmentCancelled->value)
            ->first();

        $this->actingAs($this->owner)
            ->put(route('whatsapp.templates.update', $template), [
                'whatsapp_template_name' => 'appointment_cancelled_v2',
                'language_code' => 'id',
                'preview_body' => $template->preview_body,
                'is_enabled' => '1',
                'parameters' => collect($template->parameter_map)->mapWithKeys(fn ($row) => [
                    $row['position'] => $row,
                ])->all(),
            ])
            ->assertRedirect();

        $this->assertSame('appointment_cancelled_v2', $template->fresh()->whatsapp_template_name);
        $this->assertSame(WhatsAppEvent::AppointmentCancelled, $template->fresh()->event_key);
    }

    public function test_visit_events_and_duplicate_reminder_are_prevented(): void
    {
        $this->connectWhatsApp();
        $this->enableEvent(WhatsAppEvent::AppointmentCreated);
        $this->enableEvent(WhatsAppEvent::AppointmentCancelled);
        Http::fake(['https://whatscrm.id/api/v1/send-template' => Http::response(['message_id' => 'wamid-1'], 200)]);

        $visit = $this->makeVisit();

        $this->assertDatabaseHas('whatsapp_message_logs', [
            'tenant_id' => $this->tenant->id,
            'event_key' => WhatsAppEvent::AppointmentCreated->value,
            'status' => WhatsAppMessageStatus::Sent->value,
            'template_name' => 'appointment_created',
        ]);

        $log = WhatsAppMessageLog::query()->where('event_key', WhatsAppEvent::AppointmentCreated->value)->first();
        $this->assertSame(['Budi', 'KlinikDent', 'Cabang Pati'], array_slice($log->body_params, 0, 3));
        $this->assertNotContains('{{1}}', $log->body_params);

        $this->enableEvent(WhatsAppEvent::AppointmentReminder, ['h1' => true, 'send_time' => '00:00']);
        $service = app(WhatsAppAutomationService::class);
        $first = $service->queueVisit(WhatsAppEvent::AppointmentReminder, $visit, 'h1');
        $second = $service->queueVisit(WhatsAppEvent::AppointmentReminder, $visit, 'h1');
        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(1, WhatsAppMessageLog::query()->where('event_key', WhatsAppEvent::AppointmentReminder->value)->count());

        $visit->update(['status' => VisitStatus::Cancelled]);
        $this->assertDatabaseHas('whatsapp_message_logs', [
            'event_key' => WhatsAppEvent::AppointmentCancelled->value,
            'status' => WhatsAppMessageStatus::Sent->value,
        ]);
    }

    public function test_rescheduled_today_confirmation_and_missed_use_visit_branch(): void
    {
        $other = Branch::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Cabang Rembang']);
        $this->connectWhatsApp();
        Http::fake(['https://whatscrm.id/api/v1/send-template' => Http::response(['ok' => true], 200)]);

        foreach ([
            WhatsAppEvent::AppointmentConfirmation,
            WhatsAppEvent::AppointmentToday,
            WhatsAppEvent::AppointmentRescheduled,
            WhatsAppEvent::AppointmentMissed,
        ] as $event) {
            $this->enableEvent($event);
        }

        $visit = $this->makeVisit(['branch_id' => $other->id, 'visit_date' => now()->toDateString()]);
        $visit->update(['visit_date' => now()->addDay()->toDateString()]);

        app(WhatsAppAutomationService::class)->queueVisit(WhatsAppEvent::AppointmentToday, $visit, 'today');
        app(WhatsAppAutomationService::class)->queueVisit(WhatsAppEvent::AppointmentMissed, $visit, 'missed');

        $logs = WhatsAppMessageLog::query()->where('tenant_id', $this->tenant->id)->get();
        $this->assertTrue($logs->contains(fn ($log) => $log->event_key === WhatsAppEvent::AppointmentConfirmation));
        $this->assertTrue($logs->contains(fn ($log) => $log->event_key === WhatsAppEvent::AppointmentRescheduled));
        $this->assertTrue($logs->every(fn ($log) => (int) $log->branch_id === (int) $other->id));
        $this->assertTrue($logs->every(fn ($log) => in_array('Cabang Rembang', $log->body_params, true)));
        $this->assertFalse($logs->contains(fn ($log) => in_array('Cabang Pati', $log->body_params, true)));
    }

    public function test_payment_reminder_skipped_when_invoice_paid(): void
    {
        $this->connectWhatsApp();
        $this->enableEvent(WhatsAppEvent::PaymentReminder);
        $visit = $this->makeVisit();

        $paid = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'visit_id' => $visit->id,
            'patient_id' => $this->patient->id,
            'payer_id' => $this->payer->id,
            'total_amount' => '100000.00',
            'paid_amount' => '100000.00',
            'status' => InvoiceStatus::Paid,
        ]);
        $open = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'visit_id' => $visit->id,
            'patient_id' => $this->patient->id,
            'payer_id' => $this->payer->id,
            'total_amount' => '150000.00',
            'paid_amount' => '0.00',
            'status' => InvoiceStatus::Unpaid,
        ]);

        Http::fake(['https://whatscrm.id/api/v1/send-template' => Http::response(['ok' => true], 200)]);

        $this->assertNull(app(WhatsAppAutomationService::class)->queueInvoice(WhatsAppEvent::PaymentReminder, $paid));
        $this->assertNotNull(app(WhatsAppAutomationService::class)->queueInvoice(WhatsAppEvent::PaymentReminder, $open, null, 'outstanding'));
    }

    public function test_promotion_requires_marketing_consent(): void
    {
        $this->connectWhatsApp();
        $this->enableEvent(WhatsAppEvent::PromotionGeneral);
        Http::fake(['https://whatscrm.id/api/v1/send-template' => Http::response(['ok' => true], 200)]);

        $promotion = WhatsAppPromotion::query()->create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Promo Scaling',
            'message' => 'Diskon scaling',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addWeek()->toDateString(),
            'is_enabled' => true,
        ]);

        $denied = app(WhatsAppAutomationService::class)->queuePromotion($promotion, $this->patient);
        $this->assertSame(WhatsAppMessageStatus::Failed, $denied->status);
        $this->assertSame('marketing_consent', $denied->error_code);

        $this->patient->update(['marketing_consent' => true]);
        $sent = app(WhatsAppAutomationService::class)->queuePromotion($promotion, $this->patient->fresh());
        $this->assertSame(WhatsAppMessageStatus::Sent, $sent->status);
    }

    public function test_parameter_resolver_uses_dynamic_counts(): void
    {
        $visit = $this->makeVisit();
        $context = WhatsAppMessageContext::fromVisit($visit);
        $resolver = app(WhatsAppParameterResolver::class);
        $mapping = collect(range(1, 6))->map(fn ($position, $i) => [
            'position' => $position,
            'source' => ['patient.name', 'clinic.name', 'branch.name', 'appointment.date', 'appointment.time', 'doctor.name'][$i],
        ])->all();

        $values = $resolver->resolveAll($mapping, $context);
        $this->assertCount(6, $values);
        $this->assertSame('Budi', $values[0]);
        $this->assertSame('Cabang Pati', $values[2]);
        $this->assertNotSame('{{1}}', $values[0]);
    }

    public function test_api_errors_retry_only_when_retryable(): void
    {
        $this->connectWhatsApp();
        Queue::fake();

        Http::fake([
            'https://whatscrm.id/api/v1/send-template' => Http::sequence()
                ->push(['message' => 'err'], 400)
                ->push(['message' => 'err'], 401)
                ->push(['message' => 'err'], 429)
                ->push(['message' => 'err'], 500)
                ->pushStatus(200),
        ]);

        $cases = [
            400 => false,
            401 => false,
            429 => true,
            500 => true,
        ];

        foreach ($cases as $status => $retryable) {
            $log = $this->queuedLog('api-'.$status);
            try {
                app(WhatsAppTemplateService::class)->sendLog($log);
                $this->assertFalse($retryable, 'Status '.$status.' should not be retryable');
            } catch (WhatsAppException $exception) {
                $this->assertTrue($retryable);
                $this->assertTrue($exception->retryable);
            }

            $this->assertSame(WhatsAppMessageStatus::Failed, $log->fresh()->status);
            $this->assertSame((string) $status, $log->fresh()->error_code);
        }

        $this->app->forgetInstance(Factory::class);
        Http::fake(function () {
            throw new ConnectionException('timeout');
        });

        try {
            app(WhatsAppTemplateService::class)->sendLog($this->queuedLog('api-timeout'));
            $this->fail('Timeout should retry');
        } catch (WhatsAppException $exception) {
            $this->assertTrue($exception->retryable);
            $this->assertSame('timeout', $exception->errorCode);
        }
    }

    protected function queuedLog(string $idempotency): WhatsAppMessageLog
    {
        return WhatsAppMessageLog::query()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'patient_id' => $this->patient->id,
            'event_key' => WhatsAppEvent::AppointmentCreated->value,
            'category' => 'appointment',
            'template_name' => 'appointment_created',
            'phone_number' => '6281234567890',
            'to_name' => 'Budi',
            'body_params' => ['Budi'],
            'status' => WhatsAppMessageStatus::Queued,
            'idempotency_key' => $idempotency,
            'request_payload' => [
                'phone_number' => '6281234567890',
                'to_name' => 'Budi',
                'name_template' => 'appointment_created',
                'language_code' => 'id',
                'body_params' => ['Budi'],
            ],
        ]);
    }

    public function test_tenant_isolation_for_whatsapp_pages(): void
    {
        $other = Tenant::factory()->create();
        $stranger = User::factory()->create(['tenant_id' => $other->id]);
        $stranger->assignRole(RoleName::Owner->value);

        $this->actingAs($this->owner)->get(route('whatsapp.dashboard'))->assertOk();
        $this->actingAs($stranger)->get(route('whatsapp.dashboard'))->assertOk();

        app(WhatsAppProvisioningService::class)->ensureTenant($this->tenant->id);
        $template = WhatsAppTenantTemplate::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->first();

        $this->actingAs($stranger)
            ->get(route('whatsapp.templates.edit', $template))
            ->assertForbidden();
    }

    public function test_registration_cannot_open_whatsapp(): void
    {
        $user = $this->tenantUser(RoleName::Registration);
        $this->actingAs($user)->get(route('whatsapp.dashboard'))->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeVisit(array $overrides = []): Visit
    {
        return Visit::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'patient_id' => $this->patient->id,
            'payer_id' => $this->payer->id,
            'status' => VisitStatus::Waiting,
            'visit_date' => now()->addDay()->toDateString(),
        ], $overrides));
    }

    protected function connectWhatsApp(): void
    {
        app(WhatsAppProvisioningService::class)->ensureTenant($this->tenant->id);

        $setting = WhatsAppSetting::forTenant($this->tenant->id);
        $setting->enabled = true;
        $setting->api_base_url = 'https://whatscrm.id';
        $setting->api_key = 'test-key-7890';
        $setting->business_name = 'KlinikDent';
        $setting->save();

        WhatsAppTenantTemplate::query()
            ->where('tenant_id', $this->tenant->id)
            ->update(['is_enabled' => true]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function enableEvent(WhatsAppEvent $event, array $config = []): void
    {
        WhatsAppAutomationRule::query()->updateOrCreate(
            ['tenant_id' => $this->tenant->id, 'event_key' => $event->value],
            ['is_enabled' => true, 'config' => $config],
        );
    }

    protected function tenantUser(RoleName $role): User
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
        ]);
        $user->assignRole($role->value);

        return $user;
    }
}
