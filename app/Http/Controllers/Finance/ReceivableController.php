<?php

namespace App\Http\Controllers\Finance;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Finance\Concerns\ScopesFinanceBranch;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceivableController extends Controller
{
    use ScopesFinanceBranch;

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('receivable.view'), 403);

        $query = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->with(['patient', 'payer', 'branch']);
        $this->constrainBranch($query, $request->user());

        $invoices = $query->orderBy('created_at')->get();

        $buckets = [
            '0-30' => ['label' => '0–30 hari', 'rows' => collect(), 'total' => '0.00'],
            '31-60' => ['label' => '31–60 hari', 'rows' => collect(), 'total' => '0.00'],
            '61-90' => ['label' => '61–90 hari', 'rows' => collect(), 'total' => '0.00'],
            '90+' => ['label' => '> 90 hari', 'rows' => collect(), 'total' => '0.00'],
        ];

        foreach ($invoices as $invoice) {
            $days = $invoice->created_at?->startOfDay()->diffInDays(now()->startOfDay()) ?? 0;
            $key = match (true) {
                $days <= 30 => '0-30',
                $days <= 60 => '31-60',
                $days <= 90 => '61-90',
                default => '90+',
            };
            $buckets[$key]['rows']->push($invoice);
            $buckets[$key]['total'] = bcadd($buckets[$key]['total'], $invoice->remainingAmount(), 2);
        }

        return view('finance.receivables', compact('buckets'));
    }
}
