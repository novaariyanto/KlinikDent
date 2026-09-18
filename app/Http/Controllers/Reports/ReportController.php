<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Finance\Concerns\ScopesFinanceBranch;
use App\Models\User;
use App\Models\Visit;
use App\Support\Finance\FinanceReportService;
use App\Support\Reports\ClinicReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ScopesFinanceBranch;

    public function __construct(
        protected ClinicReportService $clinic,
        protected FinanceReportService $finance,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user?->can('report.view'), 403);

        $items = collect([
            ['title' => 'Kunjungan', 'route' => 'reports.visits', 'desc' => 'Volume kunjungan per periode.', 'show' => true],
            ['title' => 'Pendapatan', 'route' => 'reports.revenue', 'desc' => 'Pembayaran masuk per hari dan metode.', 'show' => $user->can('revenue.view') || $user->can('branch.manage')],
            ['title' => 'Tindakan', 'route' => 'reports.procedures', 'desc' => 'Tindakan medis dan nilai.', 'show' => true],
            ['title' => 'Pasien', 'route' => 'reports.patients', 'desc' => 'Pasien baru dan yang berkunjung.', 'show' => true],
            ['title' => 'Farmasi', 'route' => 'reports.pharmacy', 'desc' => 'Stok, masuk, keluar, expired.', 'show' => $user->can('pharmacy.view') || $user->can('stock.view')],
            ['title' => 'Operasional', 'route' => 'reports.operational', 'desc' => 'Antrean, tagihan, resep menunggu.', 'show' => true],
            ['title' => 'Medis', 'route' => 'reports.medical', 'desc' => 'Diagnosis, tindakan, rujukan.', 'show' => true],
            ['title' => 'Keuangan', 'route' => 'reports.finance', 'desc' => 'Laba rugi, arus kas, piutang.', 'show' => $user->can('finance.view') || $user->can('branch.manage')],
            ['title' => 'Laporan pribadi', 'route' => 'reports.personal', 'desc' => 'Kinerja dokter yang login.', 'show' => $user->hasRole(\App\Enums\RoleName::Dentist)],
        ])->filter(fn (array $item) => $item['show'] && \Illuminate\Support\Facades\Route::has($item['route']));

        return view('reports.index', compact('items'));
    }

    public function visits(Request $request): View|StreamedResponse|Response
    {
        abort_unless($request->user()?->can('report.view'), 403);

        [$from, $to, $branchId, $user] = $this->context($request);
        $counts = $this->clinic->visitCounts($user, $from, $to, $branchId);
        $byDay = $this->clinic->visitsByDay($user, $from, $to, $branchId);
        $visits = $this->clinic->paginateVisits($user, $from, $to, $branchId);

        $export = $this->clinic->visitQuery($user, $from, $to, $branchId)
            ->with(['patient', 'doctor'])
            ->orderBy('visit_date')
            ->get()
            ->map(fn (Visit $visit) => [
                $visit->visit_date?->toDateString() ?? '',
                $visit->patient?->name ?? '',
                $visit->doctor?->name ?? '',
                $visit->status->label(),
            ]);

        return $this->respond($request, 'Laporan Kunjungan', ['Tanggal', 'Pasien', 'Dokter', 'Status'], $export, 'reports.visits', [
            'counts' => $counts,
            'byDay' => $byDay,
            'visits' => $visits,
        ], $from, $to, $branchId);
    }

    public function revenue(Request $request): View|StreamedResponse|Response
    {
        abort_unless($request->user()?->can('report.view'), 403);
        abort_unless($request->user()?->can('revenue.view') || $request->user()?->can('branch.manage'), 403);

        [$from, $to, $branchId] = $this->filters($request);
        $reports = $this->finance->forActor($request->user());
        $byDay = $reports->revenueByDay($from, $to, $branchId);
        $byMethod = $reports->revenueByMethod($from, $to, $branchId);
        $byDoctor = $reports->revenueByDoctor($from, $to, $branchId);
        $total = $reports->paymentSum($from, $to, $branchId);

        $rows = $byDay->map(fn ($amount, $day) => [(string) $day, $amount]);

        return $this->respond($request, 'Laporan Pendapatan', ['Tanggal', 'Nominal'], $rows, 'reports.revenue', [
            'byDay' => $byDay,
            'byMethod' => $byMethod,
            'byDoctor' => $byDoctor,
            'total' => $total,
        ], $from, $to, $branchId);
    }

    public function procedures(Request $request): View|StreamedResponse|Response
    {
        abort_unless($request->user()?->can('report.view'), 403);

        [$from, $to, $branchId, $user] = $this->context($request);
        $rowsData = $this->clinic->proceduresByName($user, $from, $to, $branchId);
        $export = $rowsData->map(fn ($row) => [$row->name, (string) $row->qty, (string) $row->amount]);

        return $this->respond($request, 'Laporan Tindakan', ['Tindakan', 'Qty', 'Nilai'], $export, 'reports.procedures', [
            'rows' => $rowsData,
            'totalQty' => $rowsData->sum(fn ($row) => (int) $row->qty),
            'totalAmount' => $rowsData->reduce(fn (string $carry, $row) => bcadd($carry, (string) $row->amount, 2), '0.00'),
        ], $from, $to, $branchId);
    }

    public function patients(Request $request): View|StreamedResponse|Response
    {
        abort_unless($request->user()?->can('report.view'), 403);

        [$from, $to, $branchId, $user] = $this->context($request);
        $summary = $this->clinic->patientSummary($user, $from, $to, $branchId);
        $export = collect([
            ['Berkunjung', (string) $summary['visited']],
            ['Pasien baru', (string) $summary['new']],
            ['Laki-laki', (string) $summary['male']],
            ['Perempuan', (string) $summary['female']],
        ]);

        return $this->respond($request, 'Laporan Pasien', ['Metrik', 'Nilai'], $export, 'reports.patients', [
            'summary' => $summary,
        ], $from, $to, $branchId);
    }

    public function personal(Request $request): View|StreamedResponse|Response
    {
        $user = $request->user();
        abort_unless($user?->can('report.view'), 403);
        abort_unless($user->hasRole(\App\Enums\RoleName::Dentist) || $user->can('branch.manage'), 403);

        [$from, $to, $branchId] = $this->filters($request);
        $doctorId = $user->hasRole(\App\Enums\RoleName::Dentist) && ! $user->can('branch.manage')
            ? (int) $user->id
            : ($request->integer('doctor_id') ?: (int) $user->id);

        $doctor = User::query()->find($doctorId) ?? $user;
        $counts = $this->clinic->visitCounts($user, $from, $to, $branchId, $doctorId);
        $procedures = $this->clinic->proceduresByName($user, $from, $to, $branchId, $doctorId);
        $revenue = $this->clinic->doctorRevenue($doctor, $from, $to, $branchId);
        $export = $procedures->map(fn ($row) => [$row->name, (string) $row->qty, (string) $row->amount]);

        return $this->respond($request, 'Laporan Pribadi', ['Tindakan', 'Qty', 'Nilai'], $export, 'reports.personal', [
            'counts' => $counts,
            'procedures' => $procedures,
            'revenue' => $revenue,
            'doctor' => $doctor,
        ], $from, $to, $branchId);
    }

    public function operational(Request $request): View|StreamedResponse|Response
    {
        abort_unless($request->user()?->can('report.view'), 403);

        [$from, $to, $branchId, $user] = $this->context($request);
        $ops = $this->clinic->operational($user, $from, $to, $branchId);
        $export = collect([
            ['Kunjungan periode', (string) $ops['total']],
            ['Hari ini', (string) $ops['today']],
            ['Selesai', (string) $ops['done']],
            ['Antrean menunggu', (string) $ops['queue_waiting']],
            ['Tagihan terbuka', (string) $ops['open_invoices']],
            ['Resep menunggu', (string) $ops['pending_rx']],
            ['Rujukan', (string) $ops['referrals']],
        ]);

        return $this->respond($request, 'Laporan Operasional', ['Metrik', 'Nilai'], $export, 'reports.operational', [
            'ops' => $ops,
        ], $from, $to, $branchId);
    }

    public function medical(Request $request): View|StreamedResponse|Response
    {
        abort_unless($request->user()?->can('report.view'), 403);

        [$from, $to, $branchId, $user] = $this->context($request);
        $diagnoses = $this->clinic->topDiagnoses($user, $from, $to, $branchId);
        $procedures = $this->clinic->proceduresByName($user, $from, $to, $branchId);
        $ops = $this->clinic->operational($user, $from, $to, $branchId);
        $export = $diagnoses->map(fn ($row) => [$row->code, $row->description, (string) $row->total]);

        return $this->respond($request, 'Laporan Medis', ['Kode', 'Deskripsi', 'Jumlah'], $export, 'reports.medical', [
            'diagnoses' => $diagnoses,
            'procedures' => $procedures,
            'referrals' => $ops['referrals'],
        ], $from, $to, $branchId);
    }

    public function finance(Request $request): View
    {
        abort_unless($request->user()?->can('report.view'), 403);
        abort_unless($request->user()?->can('finance.view') || $request->user()?->can('branch.manage'), 403);

        [$from, $to, $branchId] = $this->filters($request);
        $pl = $this->finance->forActor($request->user())->profitLoss($from, $to, $branchId);

        return view('reports.finance', [
            'pl' => $pl,
            ...$this->filterViewData($request, $from, $to, $branchId),
        ]);
    }

    /**
     * @return array{0: string, 1: string, 2: int|null, 3: \App\Models\User}
     */
    protected function context(Request $request): array
    {
        [$from, $to, $branchId] = $this->filters($request);

        return [$from, $to, $branchId, $request->user()];
    }

    /**
     * @return array<string, mixed>
     */
    protected function filterViewData(Request $request, string $from, string $to, ?int $branchId): array
    {
        return [
            'from' => $from,
            'to' => $to,
            'branchId' => $branchId,
            'branches' => $this->assignedBranches($request->user()),
            'showExport' => true,
        ];
    }

    /**
     * @param  Collection<int, list<string>>|\Illuminate\Support\Collection<string, mixed>  $exportRows
     * @param  array<string, mixed>  $viewData
     */
    protected function respond(
        Request $request,
        string $title,
        array $headers,
        Collection $exportRows,
        string $view,
        array $viewData,
        string $from,
        string $to,
        ?int $branchId,
    ): View|StreamedResponse|Response {
        $payload = [
            ...$viewData,
            ...$this->filterViewData($request, $from, $to, $branchId),
            'title' => $title,
        ];

        $export = $request->string('export')->toString();

        if ($export === 'csv') {
            return $this->csv($title, $headers, $exportRows);
        }

        if ($export === 'pdf') {
            return Pdf::loadView('reports.print.simple', [
                'title' => $title,
                'from' => $from,
                'to' => $to,
                'headers' => $headers,
                'rows' => $exportRows->values()->all(),
            ])->setPaper('a4')->download(\Illuminate\Support\Str::slug($title).'.pdf');
        }

        return view($view, $payload);
    }

    /**
     * @param  Collection<int, list<string>>  $rows
     */
    protected function csv(string $title, array $headers, Collection $rows): StreamedResponse
    {
        $filename = \Illuminate\Support\Str::slug($title).'.csv';

        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, is_array($row) ? $row : (array) $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
