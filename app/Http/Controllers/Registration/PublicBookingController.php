<?php

namespace App\Http\Controllers\Registration;

use App\Enums\PayerType;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DoctorSchedule;
use App\Models\Payer;
use App\Models\Tenant;
use App\Support\Registration\AppointmentSlots;
use App\Support\Registration\VisitRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicBookingController extends Controller
{
    public function __construct(
        protected VisitRegistrar $registrar,
        protected AppointmentSlots $slots,
    ) {}

    public function show(string $slug): View
    {
        $tenant = $this->tenant($slug);

        return view('registration.booking.form', [
            'tenant' => $tenant,
            'branches' => Branch::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function sessions(Request $request, string $slug): JsonResponse
    {
        $tenant = $this->tenant($slug);
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        $branch = Branch::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->find($data['branch_id']);

        abort_unless($branch, 404);

        $schedules = DoctorSchedule::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->active()
            ->forBranch((int) $branch->id)
            ->onDate($data['date'])
            ->with([
                'doctor' => fn ($query) => $query->withoutGlobalScopes()->with('user'),
                'room' => fn ($query) => $query->withoutGlobalScopes(),
            ])
            ->orderBy('start_time')
            ->get();

        $rows = $schedules->map(function (DoctorSchedule $schedule) use ($tenant, $data) {
            $remaining = $this->slots->remaining($schedule, $data['date'], (int) $tenant->id);

            return [
                'id' => $schedule->id,
                'doctor' => $schedule->doctor?->displayName() ?: 'Dokter',
                'time' => $schedule->timeRange(),
                'room' => $schedule->room?->name,
                'remaining' => $remaining,
            ];
        })->filter(fn (array $row) => $row['remaining'] > 0)->values();

        return response()->json(['sessions' => $rows]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        $tenant = $this->tenant($slug);

        if (filled($request->input('company_website'))) {
            return back()->with('success', 'Janji tercatat.');
        }

        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'visit_date' => ['required', 'date', 'after_or_equal:today'],
            'doctor_schedule_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'nik' => ['nullable', 'digits:16'],
            'dob' => ['nullable', 'date'],
            'company_website' => ['nullable', 'string', 'max:0'],
        ]);

        $schedule = DoctorSchedule::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereKey($data['doctor_schedule_id'])
            ->first();

        if (! $schedule || $this->slots->remaining($schedule, $data['visit_date'], (int) $tenant->id) < 1) {
            throw ValidationException::withMessages([
                'doctor_schedule_id' => 'Kuota sesi dokter sudah penuh.',
            ]);
        }

        $payer = Payer::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('type', PayerType::Umum->value)
            ->first()
            ?? Payer::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();

        if (! $payer) {
            throw ValidationException::withMessages([
                'branch_id' => 'Klinik belum memiliki penjamin umum.',
            ]);
        }

        $visit = $this->registrar->registerGuest($tenant, [
            ...$data,
            'payer_id' => $payer->id,
        ]);

        return redirect()
            ->route('booking.public', $tenant->subdomain)
            ->with('booking', [
                'number' => $visit->queue?->displayNumber(),
                'doctor' => $visit->doctor?->name,
                'date' => $visit->visit_date?->format('d M Y'),
                'patient' => $visit->patient?->name,
                'mrn' => $visit->patient?->medical_record_number,
            ]);
    }

    protected function tenant(string $slug): Tenant
    {
        $tenant = Tenant::query()->where('subdomain', $slug)->first();

        abort_unless($tenant && $tenant->status !== TenantStatus::Suspended, 404);

        return $tenant;
    }
}
