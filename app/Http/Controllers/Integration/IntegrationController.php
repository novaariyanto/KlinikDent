<?php

namespace App\Http\Controllers\Integration;

use App\Enums\InsuranceClaimStatus;
use App\Enums\IntegrationProvider;
use App\Enums\PayerType;
use App\Enums\SatuSehatStatus;
use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\StoreInsuranceClaimRequest;
use App\Http\Requests\Integration\TestSatuSehatConnectionRequest;
use App\Http\Requests\Integration\UpdateBpjsSettingsRequest;
use App\Http\Requests\Integration\UpdateSatuSehatLocationRequest;
use App\Http\Requests\Integration\UpdateSatuSehatSettingsRequest;
use App\Models\Branch;
use App\Models\BranchSatuSehatSetting;
use App\Models\Doctor;
use App\Models\InsuranceClaim;
use App\Models\IntegrationLog;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\TenantIntegration;
use App\Models\Visit;
use App\Support\Integrations\IntegrationException;
use App\Support\Integrations\IntegrationService;
use App\Support\Integrations\SatuSehatClient;
use App\Support\Integrations\SatuSehatLogView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class IntegrationController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('integration.view')
            || auth()->user()?->can('satusehat.view')
            || auth()->user()?->can('bpjs.view'), 403);

        $tenantId = (int) auth()->user()?->tenant_id;

        return view('integrations.index', [
            'satusehat' => TenantIntegration::forTenant($tenantId, IntegrationProvider::SatuSehat),
            'bpjs' => TenantIntegration::forTenant($tenantId, IntegrationProvider::Bpjs),
        ]);
    }

    public function satusehat(Request $request): View
    {
        abort_unless($request->user()?->can('satusehat.view'), 403);

        $user = $request->user();
        $tenantId = (int) $user?->tenant_id;
        $branches = $user->applyBranchLimit(Branch::query()->orderBy('name'), 'id')->get();
        $branch = $branches->firstWhere('id', (int) $request->query('branch_id')) ?? $branches->first();
        $branchSetting = $branch
            ? BranchSatuSehatSetting::query()->firstOrNew([
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->id,
            ])
            : null;
        $settings = TenantIntegration::forTenant($tenantId, IntegrationProvider::SatuSehat);
        $visits = $branch
            ? Visit::query()->with('patient')->where('branch_id', $branch->id)->where('status', VisitStatus::Done)->latest('id')->limit(30)->get()
            : collect();
        $visitErrors = IntegrationLog::query()
            ->where('provider', IntegrationProvider::SatuSehat)
            ->where('subject_type', Visit::class)
            ->whereIn('subject_id', $visits->pluck('id'))
            ->latest('id')
            ->get()
            ->unique('subject_id')
            ->keyBy('subject_id');

        $done = Visit::query()->where('branch_id', $branch?->id ?: 0)->where('status', VisitStatus::Done);

        return view('integrations.satusehat', [
            'settings' => $settings,
            'canManage' => (bool) $user?->can('integration.manage'),
            'branches' => $branches,
            'branch' => $branch,
            'branchSetting' => $branchSetting,
            'locationChoices' => $branch
                ? session('satusehat.locations.'.$branch->id, [])
                : [],
            'visits' => $visits,
            'visitErrors' => $visitErrors,
            'stats' => [
                'waiting' => (clone $done)->where(function ($query) {
                    $query->whereNull('satusehat_status')
                        ->orWhere('satusehat_status', SatuSehatStatus::Pending);
                })->count(),
                'queued' => (clone $done)->where('satusehat_status', SatuSehatStatus::Queued)->count(),
                'synced' => (clone $done)->where('satusehat_status', SatuSehatStatus::Synced)->count(),
                'failed' => (clone $done)->where('satusehat_status', SatuSehatStatus::Failed)->count(),
                'last_synced_at' => (clone $done)->max('satusehat_synced_at'),
            ],
            'readiness' => [
                'doctors_missing_nik' => Doctor::query()->where(function ($query) {
                    $query->whereNull('nik')->orWhere('nik', '');
                })->count(),
                'patients_missing_nik' => $branch
                    ? Patient::query()->whereHas('visits', fn ($query) => $query->where('branch_id', $branch->id))->where(function ($query) {
                        $query->whereNull('nik')->orWhere('nik', '');
                    })->count()
                    : 0,
            ],
            'logColumns' => [
                ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Waktu'],
                ['data' => 'action', 'name' => 'action', 'title' => 'Aksi'],
                ['data' => 'status', 'name' => 'status', 'title' => 'Status', 'searchable' => false],
                ['data' => 'error_message', 'name' => 'error_message', 'title' => 'Error'],
                ['data' => 'detail', 'name' => 'detail', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-end', 'width' => '90px'],
            ],
        ]);
    }

    public function satusehatLogs(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('satusehat.view'), 403);

        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $branchId = (int) ($validated['branch_id'] ?? 0);

        if ($branchId > 0) {
            abort_unless($request->user()?->canAccessBranch($branchId), 403);
        }

        $query = IntegrationLog::query()
            ->where('provider', IntegrationProvider::SatuSehat)
            ->when($branchId > 0, fn ($builder) => $builder->where('request_payload->branch_id', $branchId))
            ->when(filled($validated['date_from'] ?? null), fn ($builder) => $builder->whereDate('created_at', '>=', $validated['date_from']))
            ->when(filled($validated['date_to'] ?? null), fn ($builder) => $builder->whereDate('created_at', '<=', $validated['date_to']));

        return DataTables::eloquent($query)
            ->editColumn('created_at', fn (IntegrationLog $log) => $log->created_at?->timezone(config('app.timezone'))->format('d M Y H:i'))
            ->editColumn('status', fn (IntegrationLog $log) => '<span class="'.$log->status->badgeClass().'">'.e($log->status->label()).'</span>')
            ->editColumn('error_message', function (IntegrationLog $log) {
                $message = trim((string) $log->error_message);

                return $message === ''
                    ? '<span class="text-muted">-</span>'
                    : e(\Illuminate\Support\Str::limit($message, 120));
            })
            ->addColumn('detail', function (IntegrationLog $log) {
                return '<button type="button" class="btn btn-sm btn-soft-secondary js-ss-log" data-url="'.e(route('integrations.satusehat.logs.show', $log)).'">Detail</button>';
            })
            ->rawColumns(['status', 'error_message', 'detail'])
            ->toJson();
    }

    public function satusehatLogShow(Request $request, IntegrationLog $log): Response
    {
        abort_unless($request->user()?->can('satusehat.view'), 403);
        abort_unless($log->provider === IntegrationProvider::SatuSehat, 404);

        $branchId = (int) data_get($log->request_payload, 'branch_id');

        if ($branchId > 0) {
            abort_unless($request->user()?->canAccessBranch($branchId), 403);
        }

        return response()->view('integrations.partials.log-detail', [
            'detail' => SatuSehatLogView::from($log),
        ]);
    }

    public function updateSatuSehat(UpdateSatuSehatSettingsRequest $request): RedirectResponse
    {
        $branch = $this->branchForIntegration((int) $request->validated('branch_id'));
        $enabled = $request->boolean('enabled');
        $fake = $request->boolean('fake');

        $this->saveSettings(IntegrationProvider::SatuSehat, $enabled, $fake, [], []);

        $setting = BranchSatuSehatSetting::query()->firstOrNew([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
        ]);
        $data = $request->validated();
        $organizationId = $data['organization_id'] ?: null;
        $clientId = $data['client_id'] ?: null;
        $secret = $data['client_secret'] ?? null;
        $organizationChanged = (string) $setting->organization_id !== (string) $organizationId;
        $credentialsChanged = ! $setting->exists
            || $setting->environment !== $data['environment']
            || $organizationChanged
            || (string) $setting->client_id !== (string) $clientId
            || (filled($secret) && $secret !== $setting->client_secret);

        if ($organizationChanged) {
            $setting->location_id = null;
            $setting->location_name = null;
        }

        $setting->tenant_id = $branch->tenant_id;
        $setting->environment = $data['environment'];
        $setting->organization_id = $organizationId;
        $setting->client_id = $clientId;

        if (filled($secret)) {
            $setting->client_secret = $secret;
        }

        if ($credentialsChanged) {
            $setting->connection_ok = false;
            $setting->sync_enabled = false;
            $setting->validated_at = null;
            $setting->last_error = null;
            $setting->organization_name = null;
        }

        $setting->save();

        $tenant = TenantIntegration::forTenant((int) $branch->tenant_id, IntegrationProvider::SatuSehat);
        (new SatuSehatClient($tenant, $setting))->forgetToken();

        activity_log('updated', $setting, [
            'branch_id' => $branch->id,
            'environment' => $setting->environment,
            'organization_id' => $setting->organization_id,
            'enabled' => $enabled,
            'fake' => $fake,
        ], 'Pengaturan SATUSEHAT cabang diubah.', 'integrations');

        return redirect()
            ->route('integrations.satusehat', ['branch_id' => $branch->id])
            ->with('success', 'Pengaturan SATUSEHAT cabang disimpan. Uji koneksi sebelum mengaktifkan sinkronisasi.');
    }

    public function testSatuSehat(TestSatuSehatConnectionRequest $request): RedirectResponse
    {
        $branch = $this->branchForIntegration((int) $request->validated('branch_id'));
        $setting = BranchSatuSehatSetting::query()->where('branch_id', $branch->id)->first();

        if (! $setting) {
            return redirect()
                ->route('integrations.satusehat', ['branch_id' => $branch->id])
                ->with('error', 'Simpan Organization ID dan kredensial cabang terlebih dahulu.');
        }

        $tenant = TenantIntegration::forTenant((int) $branch->tenant_id, IntegrationProvider::SatuSehat);
        $result = (new SatuSehatClient($tenant, $setting))->testConnection();
        $locationIds = array_column($result['locations'], 'id');

        $setting->connection_ok = $result['ok'];
        $setting->validated_at = $result['ok'] ? now() : null;
        $setting->last_error = $result['error'] ?? null;
        $setting->organization_name = $result['organization_name'] ?? ($result['ok'] ? $setting->organization_name : null);

        if (! $result['ok'] || ($setting->location_id && ! in_array($setting->location_id, $locationIds, true))) {
            $setting->sync_enabled = false;

            if ($setting->location_id && ! in_array($setting->location_id, $locationIds, true)) {
                $setting->location_id = null;
                $setting->location_name = null;
            }
        }

        $setting->save();

        $redirect = redirect()->route('integrations.satusehat', ['branch_id' => $branch->id]);

        if (! $result['ok']) {
            return $redirect->with('error', $result['error'] ?? 'Koneksi SATUSEHAT gagal.');
        }

        session([
            'satusehat.locations.'.$branch->id => collect($result['locations'])
                ->mapWithKeys(fn (array $location) => [$location['id'] => $location['name']])
                ->all(),
        ]);

        $message = $tenant->fake
            ? 'Sandbox lokal siap. Pilih lokasi, lalu aktifkan sinkronisasi. Tidak ada panggilan ke server Kemenkes.'
            : 'Koneksi berhasil ke '.$setting->organization_name.'. Pilih lokasi pelayanan.';

        return $redirect->with('success', $message);
    }

    public function updateSatuSehatLocation(UpdateSatuSehatLocationRequest $request): RedirectResponse
    {
        $branch = $this->branchForIntegration((int) $request->validated('branch_id'));
        $setting = BranchSatuSehatSetting::query()->where('branch_id', $branch->id)->first();

        if (! $setting?->connection_ok) {
            return redirect()
                ->route('integrations.satusehat', ['branch_id' => $branch->id])
                ->with('error', 'Uji koneksi yang berhasil diperlukan sebelum memilih lokasi.');
        }

        $tenant = TenantIntegration::forTenant((int) $branch->tenant_id, IntegrationProvider::SatuSehat);

        try {
            $locations = collect((new SatuSehatClient($tenant, $setting))->locations())->keyBy('id');
        } catch (IntegrationException $exception) {
            $setting->forceFill([
                'connection_ok' => false,
                'sync_enabled' => false,
                'last_error' => $exception->getMessage(),
            ])->save();

            return redirect()
                ->route('integrations.satusehat', ['branch_id' => $branch->id])
                ->with('error', $exception->getMessage());
        }

        $locationId = (string) $request->validated('location_id');

        if (! $locations->has($locationId)) {
            return redirect()
                ->route('integrations.satusehat', ['branch_id' => $branch->id])
                ->with('error', 'Lokasi itu tidak termasuk dalam organisasi SATUSEHAT cabang ini.');
        }

        $enable = $request->boolean('sync_enabled');
        $setting->forceFill([
            'location_id' => $locationId,
            'location_name' => $locations[$locationId]['name'],
            'sync_enabled' => $enable,
            'last_error' => null,
        ])->save();

        activity_log('updated', $setting, [
            'branch_id' => $branch->id,
            'location_id' => $locationId,
            'sync_enabled' => $enable,
        ], 'Lokasi SATUSEHAT cabang diperbarui.', 'integrations');

        return redirect()
            ->route('integrations.satusehat', ['branch_id' => $branch->id])
            ->with('success', $enable
                ? 'Lokasi disimpan dan sinkronisasi cabang diaktifkan.'
                : 'Lokasi disimpan. Sinkronisasi cabang belum diaktifkan.');
    }

    public function bpjs(): View
    {
        abort_unless(auth()->user()?->can('bpjs.view'), 403);

        $tenantId = (int) auth()->user()?->tenant_id;
        $settings = TenantIntegration::forTenant($tenantId, IntegrationProvider::Bpjs);

        return view('integrations.bpjs', [
            'settings' => $settings,
            'canManage' => (bool) auth()->user()?->can('integration.manage'),
            'patients' => Patient::query()->orderBy('name')->limit(200)->get(),
            'claims' => InsuranceClaim::query()->with(['patient', 'payer', 'visit'])->latest('id')->limit(20)->get(),
            'payers' => Payer::query()->where('type', PayerType::Asuransi)->orderBy('name')->get(),
            'logs' => IntegrationLog::query()
                ->where('provider', IntegrationProvider::Bpjs)
                ->latest('id')
                ->limit(30)
                ->get(),
        ]);
    }

    public function updateBpjs(UpdateBpjsSettingsRequest $request): RedirectResponse
    {
        $this->saveSettings(
            IntegrationProvider::Bpjs,
            $request->boolean('enabled'),
            $request->boolean('fake'),
            $request->only(['cons_id', 'secret_key', 'user_key', 'base_url']),
            ['secret_key', 'user_key'],
        );

        return back()->with('success', 'Pengaturan BPJS klinik disimpan.');
    }

    public function sendSatuSehat(Request $request, IntegrationService $integrations, ?Visit $visit = null): RedirectResponse
    {
        abort_unless(auth()->user()?->can('satusehat.view') || auth()->user()?->can('integration.manage'), 403);

        if (! $visit) {
            $visit = Visit::query()->findOrFail($request->validate([
                'visit_id' => ['required', 'integer', 'exists:visits,id'],
            ])['visit_id']);
        }

        $this->authorizeVisit($visit);
        abort_unless($request->user()?->canAccessBranch((int) $visit->branch_id), 403);

        try {
            $integrations->queueSatuSehat($visit, true);
        } catch (IntegrationException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $visit->refresh();

        if ($visit->satusehat_status === SatuSehatStatus::Failed) {
            $error = IntegrationLog::query()
                ->where('subject_type', Visit::class)
                ->where('subject_id', $visit->id)
                ->latest('id')
                ->value('error_message');

            return back()->with('error', $error ?: 'Pengiriman SATUSEHAT gagal.');
        }

        activity_log('updated', $visit, [], 'Kunjungan dikirim ke SATUSEHAT.', 'integrations');

        return back()->with('success', $visit->satusehat_status === SatuSehatStatus::Synced
            ? 'Kunjungan berhasil dikirim ke SATUSEHAT.'
            : 'Pengiriman SATUSEHAT masuk antrian.');
    }

    public function checkBpjs(Request $request, IntegrationService $integrations, ?Patient $patient = null): RedirectResponse
    {
        abort_unless(auth()->user()?->can('bpjs.view') || auth()->user()?->can('integration.manage'), 403);

        $data = $request->validate([
            'patient_id' => [$patient ? 'nullable' : 'required', 'integer', 'exists:patients,id'],
            'bpjs_number' => ['nullable', 'string', 'max:20'],
        ]);

        $patient ??= Patient::query()->findOrFail($data['patient_id']);
        abort_unless(auth()->user()?->belongsToTenantId((int) $patient->tenant_id), 403);

        $card = $data['bpjs_number'] ?? null;
        if ($card) {
            $patient->update(['bpjs_number' => $card]);
        }

        try {
            $integrations->queueBpjsCheck($patient->fresh(), $card);
        } catch (IntegrationException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        activity_log('updated', $patient, [], 'Cek kepesertaan BPJS diantrikan.', 'integrations');

        return back()->with('success', 'Cek kepesertaan BPJS masuk antrian.');
    }

    public function storeClaim(StoreInsuranceClaimRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $path = null;

        if ($request->hasFile('document')) {
            $path = $request->file('document')->store('insurance-claims/'.auth()->user()?->tenant_id, 'local');
        }

        $claim = InsuranceClaim::query()->create([
            'tenant_id' => auth()->user()?->tenant_id,
            'patient_id' => $data['patient_id'],
            'visit_id' => $data['visit_id'] ?? null,
            'payer_id' => $data['payer_id'] ?? null,
            'amount' => $data['amount'],
            'notes' => $data['notes'] ?? null,
            'document_path' => $path,
            'status' => InsuranceClaimStatus::Submitted,
            'submitted_at' => now(),
        ]);

        activity_log('created', $claim, $data, 'Klaim asuransi dibuat.', 'integrations');

        return redirect()->route('integrations.bpjs')->with('success', 'Klaim asuransi disimpan.');
    }

    public function downloadClaim(InsuranceClaim $insuranceClaim): StreamedResponse
    {
        $this->authorize('view', $insuranceClaim);
        abort_unless($insuranceClaim->document_path && Storage::disk('local')->exists($insuranceClaim->document_path), 404);

        return Storage::disk('local')->download($insuranceClaim->document_path);
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  list<string>  $secretKeys
     */
    protected function saveSettings(
        IntegrationProvider $provider,
        bool $enabled,
        bool $fake,
        array $credentials,
        array $secretKeys,
    ): void {
        $tenantId = (int) auth()->user()?->tenant_id;
        abort_unless($tenantId, 403);

        $settings = TenantIntegration::forTenant($tenantId, $provider);
        $settings->enabled = $enabled;
        $settings->fake = $fake;
        $settings->credentials = $settings->mergeCredentials($credentials, $secretKeys);
        $settings->save();

        activity_log('updated', $settings, [
            'provider' => $provider->value,
            'enabled' => $enabled,
            'fake' => $fake,
        ], 'Pengaturan modul '.$provider->label().' diubah.', 'integrations');
    }

    protected function authorizeVisit(Visit $visit): void
    {
        abort_unless(auth()->user()?->belongsToTenantId((int) $visit->tenant_id), 403);
    }

    protected function branchForIntegration(int $branchId): Branch
    {
        $branch = Branch::query()->findOrFail($branchId);
        abort_unless(auth()->user()?->canAccessBranch($branch->id), 403);
        abort_unless(auth()->user()?->belongsToTenantId((int) $branch->tenant_id), 403);

        return $branch;
    }
}
