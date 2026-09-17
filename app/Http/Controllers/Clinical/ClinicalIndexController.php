<?php

namespace App\Http\Controllers\Clinical;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Support\Clinical\CareTabs;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClinicalIndexController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(CareTabs::canAccessCare($request->user()), 403);

        $meta = $this->meta($request);

        abort_unless($request->user()->can($meta['permission']), 403);

        $visits = Visit::query()
            ->visibleTo($request->user())
            ->with(['patient', 'queue', 'doctor', 'room', 'medicalRecord'])
            ->today()
            ->latest('id')
            ->paginate(20);

        return view('clinical.index', compact('visits', 'meta'));
    }

    /**
     * @return array{title: string, permission: string, tab: string}
     */
    protected function meta(Request $request): array
    {
        return match (true) {
            $request->routeIs('medical-record.index') => [
                'title' => 'Rekam Medis',
                'permission' => 'medical_record.view',
                'tab' => 'record',
            ],
            $request->routeIs('odontogram.index') => [
                'title' => 'Odontogram',
                'permission' => 'odontogram.view',
                'tab' => 'odontogram',
            ],
            $request->routeIs('diagnosis.index') => [
                'title' => 'Diagnosis',
                'permission' => 'diagnosis.view',
                'tab' => 'diagnosis',
            ],
            $request->routeIs('procedures.index') => [
                'title' => 'Tindakan',
                'permission' => 'procedure.view',
                'tab' => 'procedures',
            ],
            $request->routeIs('prescriptions.index') => [
                'title' => 'Resep',
                'permission' => 'prescription.view',
                'tab' => 'prescriptions',
            ],
            $request->routeIs('referrals.index') => [
                'title' => 'Rujukan',
                'permission' => 'referral.view',
                'tab' => 'notes',
            ],
            $request->routeIs('examinations.vitals') => [
                'title' => 'Tanda Vital',
                'permission' => 'vital_sign.view',
                'tab' => 'vitals',
            ],
            $request->routeIs('examinations.anamnesis') => [
                'title' => 'Anamnesis',
                'permission' => 'anamnesis.view',
                'tab' => 'anamnesis',
            ],
            $request->routeIs('examinations.notes') => [
                'title' => 'Catatan Pelayanan',
                'permission' => 'care_note.view',
                'tab' => 'notes',
            ],
            $request->routeIs('examinations.initial') => [
                'title' => 'Pemeriksaan Awal',
                'permission' => 'examination.view',
                'tab' => 'exam',
            ],
            default => [
                'title' => 'Pemeriksaan',
                'permission' => 'examination.view',
                'tab' => 'exam',
            ],
        };
    }
}
