<?php

namespace App\Support\Reports;

use App\Enums\InvoiceStatus;
use App\Enums\PrescriptionStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\QueueStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Models\Invoice;
use App\Models\MedicineStock;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\PurchaseOrder;
use App\Models\Queue;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Billing\BillingService;
use App\Support\Finance\FinanceReportService;
use App\Support\Pharmacy\PharmacyStockService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

class DashboardMetricsService
{
    public function __construct(
        protected ClinicReportService $clinic,
        protected FinanceReportService $finance,
        protected BillingService $billing,
    ) {
    }

    /**
     * @return array{cards: list<array{label: string, value: string, href?: string|null}>, links: list<array{label: string, href: string}>, note: string|null}
     */
    public function forUser(User $user, ?RoleName $role = null): array
    {
        $branchId = null;
        $roles = $user->getRoleNames()
            ->map(fn (string $name) => RoleName::tryFrom($name))
            ->filter()
            ->unique()
            ->values();

        if ($roles->isEmpty() && $role) {
            $roles = collect([$role]);
        }

        $fingerprint = $roles->map(fn (RoleName $assigned) => $assigned->value)->sort()->implode('|');
        $branchFingerprint = implode(',', $user->restrictedBranchIds() ?? ['all']);

        if (app()->environment('testing')) {
            return $this->mergeForRoles($user, $roles, $branchId);
        }

        $key = implode(':', ['dashboard', $user->id, $fingerprint ?: 'none', $branchFingerprint]);

        return Cache::remember($key, 120, function () use ($user, $roles, $branchId) {
            return $this->mergeForRoles($user, $roles, $branchId);
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, RoleName>  $roles
     * @return array{cards: list<array{label: string, value: string, href?: string|null}>, links: list<array{label: string, href: string}>, note: string|null}
     */
    protected function mergeForRoles(User $user, $roles, ?int $branchId): array
    {
        if ($roles->count() <= 1) {
            return $this->build($user, $roles->first(), $branchId);
        }

        $cards = [];
        $links = [];
        $note = null;
        $seenCards = [];
        $seenLinks = [];

        foreach ($roles as $role) {
            $part = $this->build($user, $role, $branchId);

            foreach ($part['cards'] as $card) {
                if (isset($seenCards[$card['label']])) {
                    continue;
                }

                $seenCards[$card['label']] = true;
                $cards[] = $card;
            }

            foreach ($part['links'] as $link) {
                if (isset($seenLinks[$link['label']])) {
                    continue;
                }

                $seenLinks[$link['label']] = true;
                $links[] = $link;
            }

            if ($note === null && ! empty($part['note'])) {
                $note = $part['note'];
            }
        }

        return compact('cards', 'links', 'note');
    }

    /**
     * @return array{cards: list<array{label: string, value: string, href?: string|null}>, links: list<array{label: string, href: string}>, note: string|null}
     */
    protected function build(User $user, ?RoleName $role, ?int $branchId): array
    {
        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();
        $today = now()->toDateString();

        return match ($role) {
            RoleName::SuperAdminSaas => $this->saas(),
            RoleName::Registration => $this->registration($user, $today, $branchId),
            RoleName::Finance => $this->finance($user, $branchId, $from, $to, $today),
            RoleName::Owner, RoleName::Manager, RoleName::Auditor => $this->clinic($user, $branchId, $from, $to, $today),
            RoleName::Dentist => $this->dentist($user, $branchId, $from, $to, $today),
            RoleName::Pharmacy => $this->pharmacy($user, $branchId),
            RoleName::Cashier => $this->cashier($user, $branchId, $today),
            RoleName::DentalAssistant, RoleName::Nurse => $this->careSupport($user, $branchId, $today),
            default => ['cards' => [], 'links' => [], 'note' => null],
        };
    }

    /**
     * @return array{cards: list<array{label: string, value: string, href?: string|null}>, links: list<array{label: string, href: string}>, note: string|null}
     */
    protected function saas(): array
    {
        return [
            'cards' => [
                $this->card('Klinik', (string) Tenant::query()->count(), $this->routeIf('saas.tenants.index')),
                $this->card('Aktif', (string) Tenant::query()->where('status', TenantStatus::Active)->count()),
                $this->card('Trial', (string) Tenant::query()->where('status', TenantStatus::Trial)->count()),
                $this->card('Suspend', (string) Tenant::query()->where('status', TenantStatus::Suspended)->count()),
                $this->card('Invoice belum lunas', (string) \App\Models\SaasInvoice::query()->where('status', \App\Enums\SaasInvoiceStatus::Unpaid)->count(), $this->routeIf('saas.invoices.index')),
            ],
            'links' => $this->links([
                'Semua Klinik' => 'saas.tenants.index',
                'Langganan' => 'saas.subscriptions.index',
                'Pengaturan Platform' => 'settings.index',
            ]),
            'note' => null,
        ];
    }

    /**
     * @return array{cards: list<array{label: string, value: string, href?: string|null}>, links: list<array{label: string, href: string}>, note: string|null}
     */
    protected function registration(User $user, string $today, ?int $branchId): array
    {
        $counts = $this->clinic->visitCounts($user, $today, $today, $branchId);

        return [
            'cards' => [
                $this->card('Kunjungan hari ini', (string) $counts['total'], $this->routeIf('registration.visits.today')),
                $this->card('Menunggu', (string) $counts['waiting'], $this->routeIf('queue.waiting')),
                $this->card('Sedang dilayani', (string) $counts['in_service']),
                $this->card('Selesai', (string) $counts['done']),
            ],
            'links' => $this->links([
                'Pendaftaran baru' => 'registration.new',
                'Antrean' => 'queue.today',
            ]),
            'note' => null,
        ];
    }

    /**
     * @return array{cards: list<array{label: string, value: string, href?: string|null}>, links: list<array{label: string, href: string}>, note: string|null}
     */
    protected function finance(User $user, ?int $branchId, string $from, string $to, string $today): array
    {
        $metrics = $this->finance->forActor($user)->dashboard($branchId, $from, $to);

        return [
            'cards' => [
                $this->card('Pendapatan hari ini', $this->money($metrics['today_revenue']), $this->routeIf('finance.revenue.daily')),
                $this->card('Pendapatan bulan ini', $this->money($metrics['period_revenue'])),
                $this->card('Pengeluaran bulan ini', $this->money($metrics['period_expenses']), $this->routeIf('finance.expenses')),
                $this->card('Saldo kas & bank', $this->money($metrics['cash_balance']), $this->routeIf('finance.cash-bank')),
                $this->card('Piutang', $this->money($metrics['receivables']), $this->routeIf('finance.receivables')),
                $this->card('Laba/Rugi', $this->money($metrics['profit_loss']['profit']), $this->routeIf('finance.reports.profit-loss')),
            ],
            'links' => $this->links([
                'Dashboard keuangan' => 'finance.index',
                'Laba rugi' => 'finance.reports.profit-loss',
            ]),
            'note' => 'Periode '.now()->translatedFormat('F Y'),
        ];
    }

    /**
     * @return array{cards: list<array{label: string, value: string, href?: string|null}>, links: list<array{label: string, href: string}>, note: string|null}
     */
    protected function clinic(User $user, ?int $branchId, string $from, string $to, string $today): array
    {
        $ops = $this->clinic->operational($user, $from, $to, $branchId);
        $todayRevenue = $this->finance->forActor($user)->paymentSum($today, $today, $branchId);
        $monthPl = $this->finance->forActor($user)->profitLoss($from, $to, $branchId);

        return [
            'cards' => [
                $this->card('Kunjungan hari ini', (string) $ops['today'], $this->routeIf('reports.visits')),
                $this->card('Kunjungan bulan ini', (string) $ops['total']),
                $this->card('Pendapatan hari ini', $this->money($todayRevenue), $this->routeIf('reports.revenue')),
                $this->card('Tagihan terbuka', (string) $ops['open_invoices'], $user->can('billing.view') ? $this->routeIf('billing.invoices') : null),
                $this->card('Antrean menunggu', (string) $ops['queue_waiting'], $user->can('queue.view') ? $this->routeIf('queue.today') : null),
                $this->card('Laba bulan ini', $this->money($monthPl['profit']), $this->routeIf('finance.reports.profit-loss')),
            ],
            'links' => $this->links([
                'Laporan klinik' => 'reports.index',
                'Kunjungan' => 'reports.visits',
            ]),
            'note' => null,
        ];
    }

    /**
     * @return array{cards: list<array{label: string, value: string, href?: string|null}>, links: list<array{label: string, href: string}>, note: string|null}
     */
    protected function dentist(User $user, ?int $branchId, string $from, string $to, string $today): array
    {
        $todayCounts = $this->clinic->visitCounts($user, $today, $today, $branchId, $user->id);
        $monthCounts = $this->clinic->visitCounts($user, $from, $to, $branchId, $user->id);
        $procedures = $this->clinic->proceduresByName($user, $from, $to, $branchId, $user->id);
        $revenue = $this->clinic->doctorRevenue($user, $from, $to, $branchId);

        return [
            'cards' => [
                $this->card('Pasien hari ini', (string) $todayCounts['total'], $this->routeIf('examinations.index')),
                $this->card('Menunggu', (string) $todayCounts['waiting']),
                $this->card('Kunjungan bulan ini', (string) $monthCounts['total'], $this->routeIf('reports.personal')),
                $this->card('Tindakan bulan ini', (string) $procedures->sum(fn ($row) => (int) $row->qty)),
                $this->card('Pendapatan bulan ini', $this->money($revenue)),
            ],
            'links' => $this->links([
                'Pemeriksaan' => 'examinations.index',
                'Laporan pribadi' => 'reports.personal',
            ]),
            'note' => null,
        ];
    }

    /**
     * @return array{cards: list<array{label: string, value: string, href?: string|null}>, links: list<array{label: string, href: string}>, note: string|null}
     */
    protected function pharmacy(User $user, ?int $branchId): array
    {
        $incoming = Prescription::query()
            ->where('status', PrescriptionStatus::Sent)
            ->whereHas('visit', fn ($visit) => $user->applyBranchLimit($visit, 'branch_id', $branchId))
            ->count();

        $expired = $user->applyBranchLimit(MedicineStock::query()->expired(), 'branch_id', $branchId)->count();
        $expiring = $user->applyBranchLimit(
            MedicineStock::query()->expiring(PharmacyStockService::EXPIRING_DAYS),
            'branch_id',
            $branchId
        )->count();
        $pendingOrders = $user->applyBranchLimit(
            PurchaseOrder::query()->where('status', PurchaseOrderStatus::Ordered),
            'branch_id',
            $branchId
        )->count();
        $lowStock = $user->applyBranchLimit(MedicineStock::query()->usable(), 'branch_id', $branchId)
            ->selectRaw('medicine_id, SUM(quantity) as qty')
            ->groupBy('medicine_id')
            ->havingRaw('SUM(quantity) < 20')
            ->get()
            ->count();

        return [
            'cards' => [
                $this->card('Resep masuk', (string) $incoming, $this->routeIf('pharmacy.prescriptions.incoming')),
                $this->card('Stok menipis', (string) $lowStock, $this->routeIf('pharmacy.stock.index')),
                $this->card('Expired / hampir', (string) ($expired + $expiring), $this->routeIf('pharmacy.stock.expired')),
                $this->card('PO menunggu', (string) $pendingOrders, $this->routeIf('pharmacy.purchases.receipts')),
            ],
            'links' => $this->links([
                'Dashboard farmasi' => 'pharmacy.index',
                'Laporan stok' => 'pharmacy.reports.stock',
            ]),
            'note' => null,
        ];
    }

    /**
     * @return array{cards: list<array{label: string, value: string, href?: string|null}>, links: list<array{label: string, href: string}>, note: string|null}
     */
    protected function cashier(User $user, ?int $branchId, string $today): array
    {
        $openInvoices = $user->applyBranchLimit(
            Invoice::query()->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial]),
            'branch_id',
            $branchId
        )->count();
        $todayPaid = Payment::query()
            ->whereDate('paid_at', $today)
            ->whereHas('invoice', fn ($invoice) => $user->applyBranchLimit($invoice, 'branch_id', $branchId))
            ->sum('amount');
        $shift = $this->billing->currentShift($user);

        return [
            'cards' => [
                $this->card('Tagihan terbuka', (string) $openInvoices, $this->routeIf('billing.invoices')),
                $this->card('Pembayaran hari ini', $this->money((string) $todayPaid), $this->routeIf('billing.payments')),
                $this->card('Shift', $shift ? 'Terbuka' : 'Belum dibuka', $this->routeIf($shift ? 'cashier.shifts.transactions' : 'cashier.shifts.open')),
            ],
            'links' => $this->links([
                'Tagihan hari ini' => 'billing.invoices.today',
                'Buka / tutup shift' => 'cashier.shifts.open',
            ]),
            'note' => $shift ? 'Shift sejak '.$shift->opened_at?->format('d M Y H:i') : 'Buka shift sebelum menerima pembayaran tunai.',
        ];
    }

    /**
     * @return array{cards: list<array{label: string, value: string, href?: string|null}>, links: list<array{label: string, href: string}>, note: string|null}
     */
    protected function careSupport(User $user, ?int $branchId, string $today): array
    {
        $counts = $this->clinic->visitCounts($user, $today, $today, $branchId);
        $waiting = Queue::query()
            ->where('status', QueueStatus::Waiting)
            ->whereHas('visit', fn ($visit) => $user->applyBranchLimit($visit, 'branch_id', $branchId))
            ->count();
        $called = Queue::query()
            ->where('status', QueueStatus::Called)
            ->whereHas('visit', fn ($visit) => $user->applyBranchLimit($visit, 'branch_id', $branchId))
            ->count();

        return [
            'cards' => [
                $this->card('Kunjungan hari ini', (string) $counts['total'], $this->routeIf('queue.today')),
                $this->card('Antrean menunggu', (string) $waiting, $this->routeIf('queue.waiting')),
                $this->card('Dipanggil', (string) $called, $this->routeIf('queue.called')),
                $this->card('Sedang dilayani', (string) $counts['in_service']),
            ],
            'links' => $this->links([
                'Antrean' => 'queue.today',
                'Pemeriksaan' => 'examinations.index',
            ]),
            'note' => null,
        ];
    }

    /**
     * @return array{label: string, value: string, href: string|null}
     */
    protected function card(string $label, string $value, ?string $href = null): array
    {
        return compact('label', 'value', 'href');
    }

    /**
     * @param  array<string, string>  $items
     * @return list<array{label: string, href: string}>
     */
    protected function links(array $items): array
    {
        $links = [];

        foreach ($items as $label => $route) {
            if (Route::has($route)) {
                $links[] = ['label' => $label, 'href' => route($route)];
            }
        }

        return $links;
    }

    protected function routeIf(string $name): ?string
    {
        return Route::has($name) ? route($name) : null;
    }

    protected function money(string $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }
}
