<?php

namespace App\Http\Controllers\Finance;

use App\Enums\DoctorFeeWaiverStatus;
use App\Enums\ProcedureAdjustmentType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Finance\Concerns\ScopesFinanceBranch;
use App\Models\DoctorFeeWaiverLog;
use App\Models\User;
use App\Support\Billing\DoctorFeeWaiverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DoctorFeeWaiverLogController extends Controller
{
    use ScopesFinanceBranch;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', DoctorFeeWaiverLog::class);

        [$from, $to, $branchId] = $this->filters($request);

        $query = DoctorFeeWaiverLog::query()
            ->with(['doctor', 'actor', 'approver', 'visit.patient', 'procedureRecord.procedure', 'branch'])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);

        $ids = $request->user()?->restrictedBranchIds();

        if ($ids !== null) {
            $query->where(function ($inner) use ($ids) {
                $inner->whereNull('branch_id')->orWhereIn('branch_id', $ids);
            });
        } elseif ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('doctor_user_id')) {
            $query->where('doctor_user_id', $request->integer('doctor_user_id'));
        }

        if ($request->filled('adjustment_type')) {
            $query->where('adjustment_type', $request->input('adjustment_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $summary = [
            'waiver' => (clone $query)->where('adjustment_type', ProcedureAdjustmentType::DoctorFeeWaiver->value)->where('status', DoctorFeeWaiverStatus::Applied->value)->sum('doctor_fee_before'),
            'discount' => (clone $query)->where('adjustment_type', ProcedureAdjustmentType::ProcedureDiscount->value)->sum(\DB::raw('discount_amount * 1')),
            'pending' => (clone $query)->where('status', DoctorFeeWaiverStatus::Pending->value)->count(),
        ];

        $logs = $query->orderByDesc('id')->paginate(30)->withQueryString();

        return view('finance.doctor-fee-waivers.logs.index', array_merge(
            $this->filterViewData($request, $from, $to, $branchId),
            [
                'logs' => $logs,
                'summary' => $summary,
                'doctors' => User::query()
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->whereHas('roles', fn ($q) => $q->where('name', 'dentist'))
                    ->orderBy('name')
                    ->get(),
                'adjustmentTypes' => ProcedureAdjustmentType::options(),
                'statuses' => collect(DoctorFeeWaiverStatus::cases())
                    ->mapWithKeys(fn (DoctorFeeWaiverStatus $status) => [$status->value => $status->label()])
                    ->all(),
            ],
        ));
    }

    public function approve(Request $request, DoctorFeeWaiverLog $doctorFeeWaiverLog): RedirectResponse
    {
        $this->authorize('approve', $doctorFeeWaiverLog);
        app(DoctorFeeWaiverService::class)->approve($doctorFeeWaiverLog, $request->user());

        return back()->with('success', 'Pembebasan jasa dokter disetujui.');
    }
}
