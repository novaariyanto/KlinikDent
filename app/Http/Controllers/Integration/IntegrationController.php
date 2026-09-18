<?php

namespace App\Http\Controllers\Integration;

use App\Enums\InsuranceClaimStatus;
use App\Enums\IntegrationProvider;
use App\Enums\PayerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Integration\StoreInsuranceClaimRequest;
use App\Http\Requests\Integration\UpdateBpjsSettingsRequest;
use App\Http\Requests\Integration\UpdateSatuSehatSettingsRequest;
use App\Models\InsuranceClaim;
use App\Models\IntegrationLog;
use App\Models\Patient;
use App\Models\Payer;
use App\Models\TenantIntegration;
use App\Models\Visit;
use App\Support\Integrations\IntegrationException;
use App\Support\Integrations\IntegrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function satusehat(): View
    {
        abort_unless(auth()->user()?->can('satusehat.view'), 403);

        $tenantId = (int) auth()->user()?->tenant_id;
        $settings = TenantIntegration::forTenant($tenantId, IntegrationProvider::SatuSehat);

        return view('integrations.satusehat', [
            'settings' => $settings,
            'canManage' => (bool) auth()->user()?->can('integration.manage'),
            'visits' => Visit::query()->with('patient')->latest('id')->limit(50)->get(),
            'logs' => IntegrationLog::query()
                ->where('provider', IntegrationProvider::SatuSehat)
                ->latest('id')
                ->limit(30)
                ->get(),
        ]);
    }

    public function updateSatuSehat(UpdateSatuSehatSettingsRequest $request): RedirectResponse
    {
        $this->saveSettings(
            IntegrationProvider::SatuSehat,
            $request->boolean('enabled'),
            $request->boolean('fake'),
            $request->only(['client_id', 'client_secret', 'organization_id', 'base_url', 'token_url']),
            ['client_secret'],
        );

        return back()->with('success', 'Pengaturan SATUSEHAT klinik disimpan.');
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

        try {
            $integrations->queueSatuSehat($visit);
        } catch (IntegrationException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        activity_log('updated', $visit, [], 'Kunjungan dikirim ke SATUSEHAT.', 'integrations');

        return back()->with('success', 'Pengiriman SATUSEHAT masuk antrian.');
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
        abort_unless($insuranceClaim->document_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($insuranceClaim->document_path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->download($insuranceClaim->document_path);
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
}
