<?php

namespace App\Http\Controllers\Portal;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Tenant;
use App\Models\Visit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PatientPortalController extends Controller
{
    public function show(string $slug): View
    {
        $tenant = $this->tenant($slug);
        $patient = $this->patientFromSession($tenant);

        $visits = $patient
            ? Visit::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('patient_id', $patient->id)
                ->with(['doctor', 'medicalRecord', 'diagnoses'])
                ->latest('visit_date')
                ->get()
            : collect();

        return view('portal.patient', compact('tenant', 'patient', 'visits'));
    }

    public function login(Request $request, string $slug): RedirectResponse
    {
        $tenant = $this->tenant($slug);
        $data = $request->validate([
            'medical_record_number' => ['required', 'string', 'max:40'],
            'dob' => ['required', 'date'],
            'nik_last4' => ['required', 'digits:4'],
        ]);

        $patient = Patient::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('medical_record_number', $data['medical_record_number'])
            ->first();

        $nik = (string) ($patient?->nik ?? '');
        $matches = $patient
            && $patient->dob
            && $patient->dob->toDateString() === $data['dob']
            && str_ends_with($nik, $data['nik_last4']);

        if (! $matches) {
            return back()->withInput()->with('error', 'Data tidak cocok.');
        }

        $request->session()->put($this->sessionKey($tenant), $patient->id);

        return redirect()->route('portal.patient', $tenant->subdomain);
    }

    public function logout(Request $request, string $slug): RedirectResponse
    {
        $tenant = $this->tenant($slug);
        $request->session()->forget($this->sessionKey($tenant));

        return redirect()->route('portal.patient', $tenant->subdomain);
    }

    public function resume(string $slug): Response
    {
        $tenant = $this->tenant($slug);
        $patient = $this->patientFromSession($tenant);
        abort_unless($patient, 403);

        $visits = Visit::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('patient_id', $patient->id)
            ->with(['doctor', 'medicalRecord.amendments', 'diagnoses', 'procedureRecords.procedure'])
            ->orderBy('visit_date')
            ->get();

        return Pdf::loadView('portal.resume-pdf', [
            'tenant' => $tenant,
            'patient' => $patient,
            'visits' => $visits,
        ])->setPaper('a4')->download('rekam-medis-'.$patient->medical_record_number.'.pdf');
    }

    protected function tenant(string $slug): Tenant
    {
        $tenant = Tenant::query()->where('subdomain', $slug)->first();
        abort_unless($tenant && $tenant->status !== TenantStatus::Suspended, 404);

        return $tenant;
    }

    protected function patientFromSession(Tenant $tenant): ?Patient
    {
        $id = session($this->sessionKey($tenant));

        if (! $id) {
            return null;
        }

        return Patient::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->find($id);
    }

    protected function sessionKey(Tenant $tenant): string
    {
        return 'patient_portal.'.$tenant->id;
    }
}
