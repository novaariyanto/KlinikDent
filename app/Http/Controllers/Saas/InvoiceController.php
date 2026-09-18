<?php

namespace App\Http\Controllers\Saas;

use App\Enums\SaasInvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\SaasInvoice;
use App\Support\Saas\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', SaasInvoice::class);

        $invoices = SaasInvoice::query()
            ->with(['tenant', 'subscription.package'])
            ->latest('id')
            ->paginate(20);

        return view('saas.invoices.index', compact('invoices'));
    }

    public function pay(SaasInvoice $saasInvoice, SubscriptionService $subscriptions): RedirectResponse
    {
        $this->authorize('update', $saasInvoice);

        if ($saasInvoice->status !== SaasInvoiceStatus::Unpaid) {
            return back()->with('error', 'Invoice ini tidak dapat ditandai lunas.');
        }

        $subscriptions->markPaid($saasInvoice);

        activity_log('updated', $saasInvoice, ['status' => 'paid'], 'Invoice '.$saasInvoice->number.' dilunasi.', 'saas');

        return back()->with('success', 'Invoice ditandai lunas. Langganan diperpanjang.');
    }
}
