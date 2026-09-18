<?php

namespace App\Http\Controllers\Clinical;

use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Support\Clinical\CareTabs;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClinicalIndexController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(CareTabs::canAccessCare($request->user()), 403);

        $meta = $this->meta($request);

        abort_unless($request->user()->can($meta['permission']), 403);

        $filters = $this->filters($request);

        $visitsQuery = Visit::query()
            ->visibleTo($request->user())
            ->with(['patient', 'queue', 'doctor', 'room', 'medicalRecord'])
            ->whereDate('visit_date', '>=', $filters['date_from'])
            ->whereDate('visit_date', '<=', $filters['date_to'])
            ->when($filters['status'], fn ($query) => $query->where('visits.status', $filters['status']))
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $like = '%'.$filters['q'].'%';

                $query->whereHas('patient', function ($patient) use ($like) {
                    $patient->where(function ($inner) use ($like) {
                        $inner->where('name', 'like', $like)
                            ->orWhere('medical_record_number', 'like', $like);
                    });
                });
            });

        if ($request->routeIs('examinations.index')) {
            $visitsQuery
                ->leftJoin('queues', 'queues.visit_id', '=', 'visits.id')
                ->orderByRaw("CASE visits.status WHEN 'waiting' THEN 1 WHEN 'in-service' THEN 2 WHEN 'done' THEN 3 ELSE 4 END")
                ->orderBy('queues.queue_number')
                ->orderBy('visits.id')
                ->select('visits.*');
        } else {
            $visitsQuery->latest('visit_date')->latest('id');
        }

        $visits = $visitsQuery
            ->paginate(20)
            ->withQueryString();

        $statuses = collect(VisitStatus::cases())
            ->mapWithKeys(fn (VisitStatus $status) => [$status->value => $status->label()])
            ->all();

        return view('clinical.index', compact('visits', 'meta', 'filters', 'statuses'));
    }

    /**
     * @return array{date_from: string, date_to: string, status: string|null, q: string}
     */
    protected function filters(Request $request): array
    {
        $validated = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', Rule::when($request->filled('date_from'), ['after_or_equal:date_from'])],
            'status' => ['nullable', Rule::enum(VisitStatus::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        if (blank($dateFrom) && blank($dateTo)) {
            $today = now()->toDateString();
            $dateFrom = $today;
            $dateTo = $today;
        } elseif (blank($dateFrom)) {
            $dateFrom = $dateTo;
        } elseif (blank($dateTo)) {
            $dateTo = $dateFrom;
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'status' => $validated['status'] ?? null,
            'q' => trim((string) ($validated['q'] ?? '')),
        ];
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
