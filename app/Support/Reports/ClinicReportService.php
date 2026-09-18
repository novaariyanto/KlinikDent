<?php

namespace App\Support\Reports;

use App\Enums\InvoiceStatus;
use App\Enums\PrescriptionStatus;
use App\Enums\QueueStatus;
use App\Enums\VisitStatus;
use App\Models\Diagnosis;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\ProcedureRecord;
use App\Models\Queue;
use App\Models\Referral;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ClinicReportService
{
    /**
     * @return \Illuminate\Database\Eloquent\Builder<Visit>
     */
    public function visitQuery(User $user, string $from, string $to, ?int $branchId, ?int $doctorId = null)
    {
        return Visit::query()
            ->visibleTo($user)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($doctorId, fn ($query) => $query->where('doctor_id', $doctorId))
            ->whereDate('visit_date', '>=', $from)
            ->whereDate('visit_date', '<=', $to);
    }

    /**
     * @return array<string, int>
     */
    public function visitCounts(User $user, string $from, string $to, ?int $branchId, ?int $doctorId = null): array
    {
        $base = $this->visitQuery($user, $from, $to, $branchId, $doctorId);

        return [
            'total' => (clone $base)->count(),
            'waiting' => (clone $base)->where('status', VisitStatus::Waiting)->count(),
            'in_service' => (clone $base)->where('status', VisitStatus::InService)->count(),
            'done' => (clone $base)->where('status', VisitStatus::Done)->count(),
            'cancelled' => (clone $base)->where('status', VisitStatus::Cancelled)->count(),
        ];
    }

    /**
     * @return Collection<string, int>
     */
    public function visitsByDay(User $user, string $from, string $to, ?int $branchId, ?int $doctorId = null): Collection
    {
        return $this->visitQuery($user, $from, $to, $branchId, $doctorId)
            ->selectRaw('visit_date, COUNT(*) as total')
            ->groupBy('visit_date')
            ->orderBy('visit_date')
            ->pluck('total', 'visit_date');
    }

    public function paginateVisits(User $user, string $from, string $to, ?int $branchId, ?int $doctorId = null): LengthAwarePaginator
    {
        return $this->visitQuery($user, $from, $to, $branchId, $doctorId)
            ->with(['patient', 'doctor', 'branch', 'payer'])
            ->latest('visit_date')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();
    }

    /**
     * @return Collection<int, object{name: string, qty: int|string, amount: string}>
     */
    public function proceduresByName(User $user, string $from, string $to, ?int $branchId, ?int $doctorId = null): Collection
    {
        $visitIds = $this->visitQuery($user, $from, $to, $branchId, $doctorId)->pluck('id');

        if ($visitIds->isEmpty()) {
            return collect();
        }

        return ProcedureRecord::query()
            ->selectRaw('procedures.name as name, SUM(procedure_records.quantity) as qty, SUM(procedure_records.quantity * procedure_records.price_at_time) as amount')
            ->join('procedures', 'procedures.id', '=', 'procedure_records.procedure_id')
            ->whereIn('procedure_records.visit_id', $visitIds)
            ->groupBy('procedures.id', 'procedures.name')
            ->orderByDesc('qty')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function patientSummary(User $user, string $from, string $to, ?int $branchId): array
    {
        $visitPatientIds = $this->visitQuery($user, $from, $to, $branchId)->pluck('patient_id')->unique()->filter();

        $newPatients = Patient::query()
            ->whereHas('visits', fn ($visits) => $user->applyBranchLimit($visits, 'branch_id', $branchId))
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->count();

        $byGender = Patient::query()
            ->whereIn('id', $visitPatientIds)
            ->selectRaw('gender, COUNT(*) as total')
            ->groupBy('gender')
            ->pluck('total', 'gender');

        return [
            'visited' => $visitPatientIds->count(),
            'new' => $newPatients,
            'male' => (int) ($byGender['male'] ?? $byGender['l'] ?? 0),
            'female' => (int) ($byGender['female'] ?? $byGender['p'] ?? 0),
        ];
    }

    /**
     * @return Collection<int, object{code: string, description: string, total: int|string}>
     */
    public function topDiagnoses(User $user, string $from, string $to, ?int $branchId, int $limit = 10): Collection
    {
        $visitIds = $this->visitQuery($user, $from, $to, $branchId)->pluck('id');

        if ($visitIds->isEmpty()) {
            return collect();
        }

        return Diagnosis::query()
            ->selectRaw('code, description, COUNT(*) as total')
            ->whereIn('visit_id', $visitIds)
            ->groupBy('code', 'description')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<string, int|string>
     */
    public function operational(User $user, string $from, string $to, ?int $branchId): array
    {
        $today = now()->toDateString();
        $visits = $this->visitCounts($user, $from, $to, $branchId);
        $todayVisits = $this->visitCounts($user, $today, $today, $branchId);

        $queueWaiting = Queue::query()
            ->where('status', QueueStatus::Waiting)
            ->whereHas('visit', fn ($visit) => $user->applyBranchLimit($visit, 'branch_id', $branchId))
            ->count();

        $openInvoices = $user->applyBranchLimit(
            Invoice::query()->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial]),
            'branch_id',
            $branchId
        )->count();

        $pendingRx = Prescription::query()
            ->where('status', PrescriptionStatus::Sent)
            ->whereHas('visit', fn ($visit) => $user->applyBranchLimit($visit, 'branch_id', $branchId))
            ->count();

        $referrals = Referral::query()
            ->whereIn('visit_id', $this->visitQuery($user, $from, $to, $branchId)->pluck('id')->all() ?: [0])
            ->count();

        return [
            ...$visits,
            'today' => $todayVisits['total'],
            'queue_waiting' => $queueWaiting,
            'open_invoices' => $openInvoices,
            'pending_rx' => $pendingRx,
            'referrals' => $referrals,
        ];
    }

    public function doctorRevenue(User $doctor, string $from, string $to, ?int $branchId): string
    {
        $amount = Payment::query()
            ->whereDate('paid_at', '>=', $from)
            ->whereDate('paid_at', '<=', $to)
            ->whereHas('invoice.visit', function ($visit) use ($doctor, $branchId) {
                $visit->where('doctor_id', $doctor->id);
                $doctor->applyBranchLimit($visit, 'branch_id', $branchId);
            })
            ->sum('amount');

        return number_format((float) $amount, 2, '.', '');
    }
}
