<?php

namespace App\Http\Controllers\Clinical;

use App\Enums\PrescriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Prescription;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PharmacyPrescriptionController extends Controller
{
    public function incoming(Request $request): View
    {
        abort_unless($request->user()?->can('prescription.view'), 403);

        $prescriptions = Prescription::query()
            ->where('status', PrescriptionStatus::Sent)
            ->with(['visit.patient', 'visit.doctor', 'doctor', 'items.medicine'])
            ->latest('id')
            ->paginate(20);

        return view('clinical.pharmacy.incoming', compact('prescriptions'));
    }

    public function show(Request $request, Prescription $prescription): View
    {
        abort_unless($request->user()?->can('prescription.view'), 403);
        abort_unless($prescription->isSent() || $prescription->status === PrescriptionStatus::Fulfilled, 403);

        $prescription->load(['visit.patient', 'visit.branch', 'doctor', 'items.medicine']);

        return view('clinical.pharmacy.show', compact('prescription'));
    }
}
