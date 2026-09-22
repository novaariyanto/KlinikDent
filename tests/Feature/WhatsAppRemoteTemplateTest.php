<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\VisitStatus;
use App\Enums\WhatsAppEvent;
use App\Enums\WhatsAppMessageStatus;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visit;
use App\Models\WhatsAppAutomationRule;
use App\Models\WhatsAppMessageLog;
use App\Models\WhatsAppSetting;
use App\Models\WhatsAppTenantTemplate;
use App\Support\WhatsApp\WhatsAppAutomationService;
use App\Support\WhatsApp\WhatsAppProvisioningService;
use App\Support\WhatsApp\WhatsAppTemplateService;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WhatsAppTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppRemoteTemplateTest extends TestCase
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
        ]);
    }

    public function test_lists_approved_remote_templates(): void
    {
        $this->connectWhatsApp();
        $this->fakeWhatsCrm();

        $this->actingAs($this->owner)
            ->getJson(route('whatsapp.templates.remote.index'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.name_template', 'appointment_created')
            ->assertJsonPath('data.0.status', 'approved');

        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && str_contains($request->url(), '/api/v1/templates')
            && ! str_contains(parse_url($request->url(), PHP_URL_PATH), '/templates/')
            && str_contains($request->url(), 'status=approved'));
    }

    public function test_remote_list_returns_401(): void
    {
        $this->connectWhatsApp();
        Http::fake(['https://whatscrm.id/api/v1/templates*' => Http::response(['message' => 'Unauthorized'], 401)]);

        $this->actingAs($this->owner)
            ->getJson(route('whatsapp.templates.remote.index'))
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthorized');
    }

    public function test_remote_detail_normalizes_placeholders(): void
    {
        $this->connectWhatsApp();
        $this->fakeWhatsCrm();

        $this->actingAs($this->owner)
            ->getJson(route('whatsapp.templates.remote.show', ['name' => 'order_shipped']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name_template', 'order_shipped')
            ->assertJsonPath('data.preview', 'Halo {{1}}, pesanan {{2}} telah dikirim ke {{3}}.')
            ->assertJsonPath('data.positions', [1, 2, 3]);
    }

    public function test_remote_detail_returns_404(): void
    {
        $this->connectWhatsApp();
        $this->fakeWhatsCrm();

        $this->actingAs($this->owner)
            ->getJson(route('whatsapp.templates.remote.show', ['name' => 'missing']))
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_sync_imports_three_mapping_rows(): void
    {
        $this->connectWhatsApp();
        $this->fakeWhatsCrm();

        $template = $this->tenantTemplate(WhatsAppEvent::AppointmentCreated);

        $this->actingAs($this->owner)
            ->post(route('whatsapp.templates.sync', $template), ['name' => 'order_shipped'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $template->refresh();
        $this->assertSame('order_shipped', $template->whatsapp_template_name);
        $this->assertSame('approved', $template->remote_status);
        $this->assertSame('Halo {{1}}, pesanan {{2}} telah dikirim ke {{3}}.', $template->preview_body);
        $this->assertCount(3, $template->parameter_map);
        $this->assertSame([1, 2, 3], array_column($template->parameter_map, 'position'));
    }

    public function test_create_to_meta_saves_pending_and_disables_template(): void
    {
        $this->connectWhatsApp();
        $this->fakeWhatsCrm();

        $template = $this->tenantTemplate(WhatsAppEvent::AppointmentCreated);
        $template->update(['is_enabled' => true]);

        $this->actingAs($this->owner)
            ->get(route('whatsapp.templates.create', $template))
            ->assertOk()
            ->assertSee('Ajukan Template Meta')
            ->assertSee('utility');

        $this->actingAs($this->owner)
            ->post(route('whatsapp.templates.store', $template), [
                'name' => 'Janji dibuat',
                'language' => 'id',
                'header_type' => 'none',
                'body_text' => 'Halo {{1}}, janji di {{2}}.',
            ])
            ->assertRedirect(route('whatsapp.templates.edit', $template));

        $template->refresh();
        $this->assertSame('appointment_created_abc', $template->whatsapp_template_name);
        $this->assertSame('pending', $template->remote_status);
        $this->assertFalse($template->is_enabled);
        $this->assertSame('Halo {{1}}, janji di {{2}}.', $template->preview_body);

        Http::assertSent(function (Request $request) {
            if ($request->method() !== 'POST' || ! str_ends_with(parse_url($request->url(), PHP_URL_PATH) ?? '', '/api/v1/templates')) {
                return false;
            }

            return $request['name'] === 'Janji dibuat'
                && $request['category'] === 'utility'
                && $request['body_text'] === 'Halo 1, janji di 2.';
        });
    }

    public function test_create_to_meta_shows_403_message(): void
    {
        $this->connectWhatsApp();
        Http::fake([
            'https://whatscrm.id/api/v1/templates' => Http::response(['message' => 'Pengajuan mandiri dinonaktifkan'], 403),
            'https://whatscrm.id/api/v1/templates*' => Http::response(['message' => 'Pengajuan mandiri dinonaktifkan'], 403),
        ]);

        $template = $this->tenantTemplate(WhatsAppEvent::AppointmentCreated);

        $this->actingAs($this->owner)
            ->from(route('whatsapp.templates.create', $template))
            ->post(route('whatsapp.templates.store', $template), [
                'name' => 'Janji dibuat',
                'language' => 'id',
                'header_type' => 'none',
                'body_text' => 'Halo {{1}}',
            ])
            ->assertRedirect(route('whatsapp.templates.create', $template))
            ->assertSessionHas('error', 'Pengajuan mandiri dinonaktifkan');
    }

    public function test_edit_page_shows_remote_picker_and_status(): void
    {
        $this->connectWhatsApp();
        $this->fakeWhatsCrm();

        $template = $this->tenantTemplate(WhatsAppEvent::AppointmentCreated);
        $template->update([
            'remote_status' => 'approved',
            'remote_meta' => ['display_name' => 'Janji dibuat'],
        ]);

        $this->actingAs($this->owner)
            ->get(route('whatsapp.templates.edit', $template))
            ->assertOk()
            ->assertSee('Pilih template approved WhatsCRM')
            ->assertSee('Ambil dari WhatsCRM')
            ->assertSee('Ajukan ke Meta')
            ->assertSee('approved')
            ->assertSee('Janji dibuat');
    }

    public function test_pending_template_is_not_queued_for_send(): void
    {
        $this->connectWhatsApp();
        Http::fake(['https://whatscrm.id/api/v1/send-template' => Http::response(['message_id' => 'wamid-1'], 200)]);

        $this->enableEvent(WhatsAppEvent::AppointmentCreated);
        $this->tenantTemplate(WhatsAppEvent::AppointmentCreated)->update([
            'is_enabled' => true,
            'remote_status' => 'pending',
        ]);

        $visit = Visit::factory()->create([
            'tenant_id' => $this->tenant->id,
            'branch_id' => $this->branch->id,
            'patient_id' => $this->patient->id,
            'payer_id' => $this->payer->id,
            'status' => VisitStatus::Waiting,
            'visit_date' => now()->addDay()->toDateString(),
        ]);

        $log = app(WhatsAppAutomationService::class)->queueVisit(WhatsAppEvent::AppointmentCreated, $visit);

        $this->assertNotNull($log);
        $this->assertSame(WhatsAppMessageStatus::Failed, $log->status);
        $this->assertSame('template_not_approved', $log->error_code);
        Http::assertNothingSent();
        $this->assertDatabaseMissing('whatsapp_message_logs', [
            'event_key' => WhatsAppEvent::AppointmentCreated->value,
            'status' => WhatsAppMessageStatus::Sent->value,
        ]);
    }

    public function test_send_log_skips_pending_template(): void
    {
        $this->connectWhatsApp();
        Http::fake(['https://whatscrm.id/api/v1/send-template' => Http::response(['message_id' => 'wamid-1'], 200)]);

        $this->tenantTemplate(WhatsAppEvent::AppointmentCreated)->update([
            'is_enabled' => true,
            'remote_status' => 'rejected',
        ]);

        $log = WhatsAppMessageLog::query()->create([
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
            'idempotency_key' => 'pending-skip',
            'request_payload' => [
                'phone_number' => '6281234567890',
                'to_name' => 'Budi',
                'name_template' => 'appointment_created',
                'language_code' => 'id',
                'body_params' => ['Budi'],
            ],
        ]);

        $sent = app(WhatsAppTemplateService::class)->sendLog($log);

        $this->assertSame(WhatsAppMessageStatus::Failed, $sent->status);
        $this->assertSame('template_not_approved', $sent->error_code);
        Http::assertNothingSent();
    }

    protected function fakeWhatsCrm(): void
    {
        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?? '';
            $method = $request->method();

            if ($method === 'GET' && $path === '/api/v1/templates') {
                return Http::response([
                    'success' => true,
                    'data' => [[
                        'name_template' => 'appointment_created',
                        'display_name' => 'Janji dibuat',
                        'status' => 'approved',
                        'language' => 'id',
                    ]],
                ], 200);
            }

            if ($method === 'GET' && str_starts_with($path, '/api/v1/templates/')) {
                $name = rawurldecode(basename($path));

                if ($name === 'missing') {
                    return Http::response(['message' => 'Template tidak ditemukan.'], 404);
                }

                return Http::response([
                    'success' => true,
                    'data' => [
                        'id' => 12,
                        'name_template' => $name,
                        'display_name' => 'Pesanan dikirim',
                        'status' => 'approved',
                        'language' => 'id',
                        'body' => 'Halo 1, pesanan 2 telah dikirim ke 3.',
                        'placeholders' => ['1', '2', '3'],
                    ],
                ], 200);
            }

            if ($method === 'POST' && $path === '/api/v1/templates') {
                return Http::response([
                    'success' => true,
                    'data' => [
                        'id' => 99,
                        'name_template' => 'appointment_created_abc',
                        'display_name' => 'Janji dibuat',
                        'status' => 'pending',
                        'language' => 'id',
                        'body' => 'Halo 1, janji di 2.',
                        'placeholders' => ['1', '2'],
                    ],
                ], 200);
            }

            return Http::response(['message' => 'Unexpected '.$method.' '.$path], 500);
        });
    }

    protected function tenantTemplate(WhatsAppEvent $event): WhatsAppTenantTemplate
    {
        return WhatsAppTenantTemplate::query()
            ->where('tenant_id', $this->tenant->id)
            ->where('event_key', $event->value)
            ->firstOrFail();
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
