<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Finance\Concerns\ScopesFinanceBranch;
use App\Models\SharingFeeSettlement;
use App\Models\User;
use App\Support\Finance\SharingFee\SharingFeeReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SharingFeeReportController extends Controller
{
    use ScopesFinanceBranch;

    public function __construct(protected SharingFeeReportService $reports) {}

    public function doctors(Request $request): View|StreamedResponse
    {
        $this->authorize('viewAny', SharingFeeSettlement::class);

        [$from, $to, $branchId] = $this->filters($request);
        $doctorUserId = $request->filled('doctor_user_id') ? $request->integer('doctor_user_id') : null;

        if ($request->string('export')->toString() === 'csv') {
            return $this->exportCsv($request->user(), $from, $to, $branchId, $doctorUserId);
        }

        $summary = $this->reports->summaryByDoctor($request->user(), $from, $to, $branchId);
        $details = $this->reports
            ->detailsQuery($request->user(), $from, $to, $branchId, $doctorUserId)
            ->paginate(30)
            ->withQueryString();

        return view('finance.sharing-fees.reports.doctors', array_merge(
            $this->filterViewData($request, $from, $to, $branchId),
            [
                'summary' => $summary,
                'details' => $details,
                'doctorUserId' => $doctorUserId,
                'doctors' => User::query()
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->whereHas('roles', fn ($q) => $q->where('name', 'dentist'))
                    ->orderBy('name')
                    ->get(),
            ],
        ));
    }

    protected function exportCsv(
        User $actor,
        string $from,
        string $to,
        ?int $branchId,
        ?int $doctorUserId,
    ): StreamedResponse {
        $export = $this->reports->exportRows($actor, $from, $to, $branchId, $doctorUserId);
        $filename = 'sharing-fee-'.$from.'-'.$to.'.csv';

        return response()->streamDownload(function () use ($export) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, $export['headers']);
            foreach ($export['rows'] as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
