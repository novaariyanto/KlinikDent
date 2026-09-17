<?php

namespace App\Http\Controllers\Clinical;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatientHistoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('patient_history.view'), 403);

        $term = trim((string) $request->query('q', ''));

        $patients = Patient::query()
            ->when($term !== '', fn ($query) => $query->search($term))
            ->with(['visits' => fn ($query) => $query->latest('visit_date')->latest('id')->limit(1)])
            ->whereHas('visits', fn ($query) => $query->visibleTo($request->user()))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('clinical.history.index', compact('patients', 'term'));
    }

    public function show(Request $request, Patient $patient): View
    {
        $this->authorize('view', $patient);
        abort_unless($request->user()?->can('patient_history.view'), 403);

        $visits = Visit::query()
            ->where('patient_id', $patient->id)
            ->with([
                'queue',
                'doctor',
                'room',
                'payer',
                'medicalRecord',
                'diagnoses',
                'procedureRecords.procedure',
                'prescriptions.items.medicine',
                'referrals',
            ])
            ->latest('visit_date')
            ->latest('id')
            ->get();

        $patient->load('odontogramTeeth');

        return view('clinical.history.show', compact('patient', 'visits'));
    }
}
