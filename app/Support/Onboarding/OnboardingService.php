<?php

namespace App\Support\Onboarding;

use App\Enums\IntegrationProvider;
use App\Enums\OnboardingStatus;
use App\Enums\OnboardingTemplate;
use App\Enums\PayerType;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\ClinicSetting;
use App\Models\Doctor;
use App\Models\Medicine;
use App\Models\Payer;
use App\Models\Procedure;
use App\Models\Service;
use App\Models\Tariff;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Models\User;
use App\Support\ClinicSettings;
use App\Support\Doctors\DoctorService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OnboardingService
{
    public function __construct(protected DoctorService $doctors) {}

    public function currentStepKey(Tenant $tenant): string
    {
        return OnboardingCatalog::stepKey((int) $tenant->onboarding_current_step);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveTemplate(User $actor, array $data): Tenant
    {
        $tenant = $this->tenantOf($actor);
        $template = OnboardingTemplate::from((string) $data['template']);

        return DB::transaction(function () use ($tenant, $template) {
            $tenant->update([
                'onboarding_status' => OnboardingStatus::InProgress,
                'onboarding_template' => $template,
                'onboarding_selected_roles' => OnboardingCatalog::recommendedRoles($template),
                'onboarding_current_step' => 2,
            ]);

            return $tenant->fresh() ?? $tenant;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveRoles(User $actor, array $data): Tenant
    {
        $tenant = $this->tenantOf($actor);
        $roles = array_values(array_unique(array_map('strval', $data['roles'] ?? [])));

        if (! in_array(RoleName::Owner->value, $roles, true)) {
            $roles[] = RoleName::Owner->value;
        }

        foreach ($roles as $role) {
            if ($role === RoleName::SuperAdminSaas->value || ! RoleName::isSystem($role)) {
                throw ValidationException::withMessages([
                    'roles' => 'Role tidak valid untuk klinik.',
                ]);
            }
        }

        return DB::transaction(function () use ($tenant, $roles) {
            $tenant->update([
                'onboarding_status' => OnboardingStatus::InProgress,
                'onboarding_selected_roles' => array_values(array_unique($roles)),
                'onboarding_current_step' => max((int) $tenant->onboarding_current_step, 3),
            ]);

            return $tenant->fresh() ?? $tenant;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveProfile(User $actor, array $data, ?UploadedFile $logo = null): Tenant
    {
        $tenant = $this->tenantOf($actor);
        $tenantId = (int) $tenant->id;

        return DB::transaction(function () use ($tenant, $tenantId, $data, $logo) {
            $values = [
                'clinic_name' => $data['clinic_name'],
                'tagline' => $data['tagline'] ?? '',
                'address' => $data['address'],
                'phone' => $data['phone'] ?? '',
                'email' => $data['email'] ?? '',
                'npwp' => $data['npwp'] ?? '',
                'license_number' => $data['license_number'] ?? '',
                'rm_prefix' => $data['rm_prefix'] ?? '',
            ];

            foreach ($values as $key => $value) {
                ClinicSetting::setValue($key, $value === '' ? null : (string) $value, $tenantId);
            }

            if ($logo) {
                $this->deleteStoredLogo($tenantId);
                ClinicSetting::setValue(
                    'logo',
                    $logo->store('clinic-logos/'.$tenantId, 'public'),
                    $tenantId
                );
            }

            if ($tenant->name !== $data['clinic_name']) {
                $tenant->update(['name' => $data['clinic_name']]);
            }

            $tenant->update([
                'onboarding_status' => OnboardingStatus::InProgress,
                'onboarding_current_step' => max((int) $tenant->onboarding_current_step, 4),
            ]);

            return $tenant->fresh() ?? $tenant;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveBranch(User $actor, array $data): Branch
    {
        $tenant = $this->tenantOf($actor);

        return DB::transaction(function () use ($tenant, $actor, $data) {
            $branch = $tenant->branches()->orderBy('id')->first();

            $payload = [
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ];

            if ($branch) {
                $branch->update($payload);
            } else {
                $branch = Branch::query()->create($payload);
            }

            if (! $actor->branch_id) {
                $actor->syncAssignedBranches([$branch->id]);
            }

            $tenant->update([
                'onboarding_status' => OnboardingStatus::InProgress,
                'onboarding_current_step' => max((int) $tenant->onboarding_current_step, 5),
            ]);

            return $branch->fresh() ?? $branch;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveDoctors(User $actor, array $data): void
    {
        $tenant = $this->tenantOf($actor);
        $branch = $this->primaryBranch($tenant);
        $items = array_values(array_filter($data['doctors'] ?? [], fn ($row) => filled($row['name'] ?? null)));

        DB::transaction(function () use ($actor, $tenant, $branch, $items) {
            foreach ($items as $item) {
                $name = trim(implode(' ', array_filter([
                    $item['title'] ?? null,
                    $item['name'],
                ])));

                $this->doctors->create($actor, [
                    'source' => 'new',
                    'name' => $name,
                    'email' => $item['email'],
                    'password' => $item['password'],
                    'role' => RoleName::Dentist->value,
                    'branch_ids' => [$branch->id],
                    'sip' => $item['sip'] ?? null,
                    'specialization' => $item['specialization'] ?? null,
                    'phone' => $item['phone'] ?? null,
                    'is_active' => true,
                ]);
            }

            $tenant->update([
                'onboarding_status' => OnboardingStatus::InProgress,
                'onboarding_current_step' => max((int) $tenant->onboarding_current_step, 6),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveStaff(User $actor, array $data): void
    {
        $tenant = $this->tenantOf($actor);
        $branch = $this->primaryBranch($tenant);
        $items = array_values(array_filter($data['staff'] ?? [], fn ($row) => filled($row['name'] ?? null)));

        DB::transaction(function () use ($tenant, $branch, $items) {
            foreach ($items as $item) {
                $roles = array_values(array_unique(array_map('strval', $item['roles'] ?? [])));
                $roles = array_values(array_filter(
                    $roles,
                    fn (string $role) => $role !== RoleName::SuperAdminSaas->value && in_array($role, RoleName::tenantValues(), true)
                ));

                if ($roles === []) {
                    throw ValidationException::withMessages([
                        'staff' => 'Setiap staf harus memiliki minimal satu role.',
                    ]);
                }

                $user = User::query()->create([
                    'name' => $item['name'],
                    'email' => $item['email'],
                    'password' => $item['password'],
                    'status' => UserStatus::Active,
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branch->id,
                ]);
                $user->syncRoles($roles);
                $user->syncAssignedBranches([$branch->id]);
            }

            $tenant->update([
                'onboarding_status' => OnboardingStatus::InProgress,
                'onboarding_current_step' => max((int) $tenant->onboarding_current_step, 7),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveServices(User $actor, array $data): void
    {
        $tenant = $this->tenantOf($actor);
        $mode = (string) ($data['mode'] ?? 'empty');

        DB::transaction(function () use ($tenant, $mode) {
            if ($mode === 'template') {
                $this->seedServiceTemplate($tenant);
            }

            $tenant->update([
                'onboarding_status' => OnboardingStatus::InProgress,
                'onboarding_current_step' => max((int) $tenant->onboarding_current_step, 8),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveMedicines(User $actor, array $data): void
    {
        $tenant = $this->tenantOf($actor);
        $mode = (string) ($data['mode'] ?? 'skip');

        DB::transaction(function () use ($tenant, $mode, $data) {
            if ($mode === 'template') {
                foreach (OnboardingCatalog::medicineTemplate() as $item) {
                    Medicine::query()->updateOrCreate(
                        ['tenant_id' => $tenant->id, 'name' => $item['name']],
                        [
                            'unit' => $item['unit'],
                            'category' => $item['category'],
                            'base_price' => $item['base_price'],
                            'is_active' => true,
                        ]
                    );
                }
            }

            if ($mode === 'manual') {
                foreach ($data['medicines'] ?? [] as $item) {
                    if (! filled($item['name'] ?? null)) {
                        continue;
                    }

                    Medicine::query()->updateOrCreate(
                        ['tenant_id' => $tenant->id, 'name' => $item['name']],
                        [
                            'unit' => $item['unit'] ?? 'tablet',
                            'category' => $item['category'] ?? 'lainnya',
                            'base_price' => $item['base_price'] ?? 0,
                            'is_active' => true,
                        ]
                    );
                }
            }

            $tenant->update([
                'onboarding_status' => OnboardingStatus::InProgress,
                'onboarding_current_step' => max((int) $tenant->onboarding_current_step, 9),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveIntegrations(User $actor, array $data): void
    {
        $tenant = $this->tenantOf($actor);

        DB::transaction(function () use ($tenant, $data) {
            if (($data['satusehat_action'] ?? 'skip') === 'configure') {
                $this->saveIntegration(
                    $tenant,
                    IntegrationProvider::SatuSehat,
                    array_filter([
                        'client_id' => $data['satusehat']['client_id'] ?? null,
                        'client_secret' => $data['satusehat']['client_secret'] ?? null,
                        'organization_id' => $data['satusehat']['organization_id'] ?? null,
                        'base_url' => $data['satusehat']['base_url'] ?? null,
                        'token_url' => $data['satusehat']['token_url'] ?? null,
                    ], fn ($value) => $value !== null && $value !== ''),
                    ['client_secret'],
                );
            }

            if (($data['bpjs_action'] ?? 'skip') === 'configure') {
                $this->saveIntegration(
                    $tenant,
                    IntegrationProvider::Bpjs,
                    array_filter([
                        'cons_id' => $data['bpjs']['cons_id'] ?? null,
                        'secret_key' => $data['bpjs']['secret_key'] ?? null,
                        'user_key' => $data['bpjs']['user_key'] ?? null,
                        'base_url' => $data['bpjs']['base_url'] ?? null,
                    ], fn ($value) => $value !== null && $value !== ''),
                    ['secret_key', 'user_key'],
                );
            }

            $tenant->update([
                'onboarding_status' => OnboardingStatus::InProgress,
                'onboarding_current_step' => max((int) $tenant->onboarding_current_step, 10),
            ]);
        });
    }

    public function skipStep(User $actor, string $step): Tenant
    {
        if (! OnboardingCatalog::isOptional($step)) {
            throw ValidationException::withMessages([
                'step' => 'Langkah ini tidak dapat dilewati.',
            ]);
        }

        $tenant = $this->tenantOf($actor);
        $current = max(1, (int) $tenant->onboarding_current_step);
        $stepNumber = OnboardingCatalog::stepNumber($step);

        if ($current < $stepNumber) {
            throw ValidationException::withMessages([
                'step' => 'Selesaikan langkah sebelumnya terlebih dahulu.',
            ]);
        }

        $next = min(OnboardingCatalog::TOTAL_STEPS, $stepNumber + 1);

        $tenant->update([
            'onboarding_status' => OnboardingStatus::InProgress,
            'onboarding_current_step' => max((int) $tenant->onboarding_current_step, $next),
        ]);

        return $tenant->fresh() ?? $tenant;
    }

    public function goBack(User $actor): Tenant
    {
        $tenant = $this->tenantOf($actor);
        $current = max(1, (int) $tenant->onboarding_current_step);

        $tenant->update([
            'onboarding_current_step' => max(1, $current - 1),
            'onboarding_status' => OnboardingStatus::InProgress,
        ]);

        return $tenant->fresh() ?? $tenant;
    }

    public function complete(User $actor): Tenant
    {
        $tenant = $this->tenantOf($actor);
        $settings = ClinicSettings::current((int) $tenant->id);

        if (! filled($settings['address'] ?? null)) {
            throw ValidationException::withMessages([
                'profile' => 'Lengkapi nama dan alamat klinik sebelum menyelesaikan setup.',
            ]);
        }

        if ($tenant->branches()->doesntExist()) {
            throw ValidationException::withMessages([
                'branch' => 'Klinik harus memiliki minimal satu cabang.',
            ]);
        }

        return DB::transaction(function () use ($tenant) {
            $tenant->update([
                'onboarding_status' => OnboardingStatus::Completed,
                'onboarding_current_step' => OnboardingCatalog::TOTAL_STEPS,
                'onboarding_completed_at' => now(),
            ]);

            return $tenant->fresh() ?? $tenant;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Tenant $tenant): array
    {
        $satusehat = TenantIntegration::forTenant((int) $tenant->id, IntegrationProvider::SatuSehat);
        $bpjs = TenantIntegration::forTenant((int) $tenant->id, IntegrationProvider::Bpjs);
        $staffCount = User::query()
            ->where('tenant_id', $tenant->id)
            ->whereKeyNot(auth()->id())
            ->count();

        return [
            'template' => $tenant->onboarding_template?->label() ?? '-',
            'branches' => $tenant->branches()->count(),
            'doctors' => Doctor::query()->where('tenant_id', $tenant->id)->count(),
            'staff' => $staffCount,
            'roles' => count($tenant->onboarding_selected_roles ?? []),
            'services' => Service::query()->where('tenant_id', $tenant->id)->count(),
            'medicines' => Medicine::query()->where('tenant_id', $tenant->id)->count(),
            'satusehat' => $satusehat->isConfigured(),
            'bpjs' => $bpjs->isConfigured(),
        ];
    }

    protected function seedServiceTemplate(Tenant $tenant): void
    {
        $payer = Payer::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Umum'],
            ['type' => PayerType::Umum]
        );

        foreach (OnboardingCatalog::serviceTemplate() as $item) {
            $service = Service::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $item['name']],
                ['category' => $item['category'], 'is_active' => true]
            );

            $procedure = Procedure::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => $item['code']],
                [
                    'name' => $item['procedure'],
                    'category' => $item['category'],
                    'service_id' => $service->id,
                    'is_active' => true,
                ]
            );

            Tariff::query()->updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'procedure_id' => $procedure->id,
                    'branch_id' => null,
                    'payer_id' => $payer->id,
                    'effective_date' => now()->toDateString(),
                ],
                ['price' => $item['price']]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  list<string>  $secretKeys
     */
    protected function saveIntegration(Tenant $tenant, IntegrationProvider $provider, array $credentials, array $secretKeys): void
    {
        $settings = TenantIntegration::forTenant((int) $tenant->id, $provider);
        $settings->enabled = true;
        $settings->fake = false;
        $settings->credentials = $settings->mergeCredentials($credentials, $secretKeys);
        $settings->save();
    }

    protected function tenantOf(User $actor): Tenant
    {
        $tenant = $actor->tenant;

        if (! $tenant instanceof Tenant) {
            throw ValidationException::withMessages([
                'tenant' => 'Klinik tidak ditemukan.',
            ]);
        }

        return $tenant;
    }

    protected function primaryBranch(Tenant $tenant): Branch
    {
        $branch = $tenant->branches()->orderBy('id')->first();

        if (! $branch) {
            throw ValidationException::withMessages([
                'branch' => 'Buat cabang terlebih dahulu.',
            ]);
        }

        return $branch;
    }

    protected function deleteStoredLogo(int $tenantId): void
    {
        $path = ClinicSetting::getValue('logo', null, $tenantId);

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
