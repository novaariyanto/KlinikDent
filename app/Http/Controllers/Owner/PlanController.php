<?php

namespace App\Http\Controllers\Owner;

use App\Enums\SaasInvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\SaasInvoice;
use App\Models\SaasPackage;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Saas\MidtransException;
use App\Support\Saas\MidtransService;
use App\Support\Saas\PaymentGatewaySettings;
use App\Support\Saas\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('plan.view'), 403);

        $tenant = $this->tenant();
        $subscription = $tenant->currentSubscription();
        $unpaidInvoice = $subscription
            ? $subscription->invoices()->where('status', SaasInvoiceStatus::Unpaid)->latest('id')->first()
            : $tenant->invoices()->where('status', SaasInvoiceStatus::Unpaid)->latest('id')->first();

        return view('owner.subscription.index', [
            'tenant' => $tenant,
            'subscription' => $subscription,
            'unpaidInvoice' => $unpaidInvoice,
            'packages' => SaasPackage::query()->where('is_active', true)->orderBy('price')->orderBy('name')->get(),
            'canSubscribe' => (bool) auth()->user()?->can('plan.subscribe'),
            'canPay' => (bool) auth()->user()?->can('plan.pay'),
        ]);
    }

    public function invoices(): View
    {
        abort_unless(auth()->user()?->can('plan.view'), 403);

        $tenant = $this->tenant();

        return view('owner.subscription.invoices', [
            'tenant' => $tenant,
            'subscription' => $tenant->currentSubscription(),
            'invoices' => $tenant->invoices()
                ->with('subscription.package')
                ->latest('id')
                ->paginate(20),
            'canPay' => (bool) auth()->user()?->can('plan.pay'),
            'midtransReady' => PaymentGatewaySettings::isReady(),
        ]);
    }

    public function subscribe(SaasPackage $saasPackage, SubscriptionService $subscriptions): RedirectResponse
    {
        abort_unless(auth()->user()?->can('plan.subscribe'), 403);
        abort_unless($saasPackage->is_active, 404);

        $result = $subscriptions->subscribeForOwner($this->tenant(), $saasPackage);

        activity_log(
            'updated',
            $result['subscription'],
            ['package_id' => $saasPackage->id],
            'Owner memilih paket '.$saasPackage->name.'.',
            'subscription'
        );

        if ($result['invoice']) {
            return redirect()
                ->route('subscription.invoices')
                ->with('success', $result['message']);
        }

        return redirect()
            ->route('subscription.index')
            ->with('success', $result['message']);
    }

    public function pay(SaasInvoice $saasInvoice, SubscriptionService $subscriptions, MidtransService $midtrans): RedirectResponse
    {
        abort_unless(auth()->user()?->can('plan.pay'), 403);

        $tenant = $this->tenant();
        abort_unless((int) $saasInvoice->tenant_id === (int) $tenant->id, 403);

        if ($saasInvoice->status !== SaasInvoiceStatus::Unpaid) {
            return redirect()->route('subscription.invoices')->with('error', 'Tagihan ini tidak dapat dibayar.');
        }

        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        if ($midtrans->isReady()) {
            try {
                $midtrans->createSnapToken($saasInvoice->load('subscription.package'), $user);
            } catch (MidtransException $exception) {
                return redirect()->route('subscription.invoices')->with('error', $exception->getMessage());
            }

            return redirect()->route('subscription.invoices.checkout', $saasInvoice);
        }

        $invoice = $subscriptions->payForTenant($tenant, $saasInvoice);

        activity_log(
            'updated',
            $invoice,
            ['status' => 'paid', 'channel' => 'manual'],
            'Owner membayar invoice '.$invoice->number.'.',
            'subscription'
        );

        return redirect()
            ->route('subscription.invoices')
            ->with('success', 'Pembayaran berhasil. Paket langganan aktif.');
    }

    public function checkout(SaasInvoice $saasInvoice): View|RedirectResponse
    {
        abort_unless(auth()->user()?->can('plan.pay'), 403);

        $tenant = $this->tenant();
        abort_unless((int) $saasInvoice->tenant_id === (int) $tenant->id, 403);

        if ($saasInvoice->status !== SaasInvoiceStatus::Unpaid || ! $saasInvoice->midtrans_snap_token) {
            return redirect()->route('subscription.invoices')->with('error', 'Sesi pembayaran tidak tersedia. Silakan bayar ulang.');
        }

        return view('owner.subscription.checkout', [
            'invoice' => $saasInvoice->load('subscription.package'),
            'snapToken' => $saasInvoice->midtrans_snap_token,
            'clientKey' => PaymentGatewaySettings::clientKey(),
            'snapScriptUrl' => PaymentGatewaySettings::snapScriptUrl(),
            'finishUrl' => route('subscription.invoices.finish', ['order_id' => $saasInvoice->midtrans_order_id]),
            'invoicesUrl' => route('subscription.invoices'),
        ]);
    }

    public function finish(Request $request, MidtransService $midtrans): RedirectResponse
    {
        abort_unless(auth()->user()?->can('plan.view'), 403);

        $orderId = (string) $request->query('order_id', '');
        $invoice = $midtrans->syncByOrderId($orderId, (int) $this->tenant()->id);

        if ($invoice && $invoice->status === SaasInvoiceStatus::Paid) {
            return redirect()
                ->route('subscription.invoices')
                ->with('success', 'Pembayaran berhasil. Paket langganan aktif.');
        }

        return redirect()
            ->route('subscription.invoices')
            ->with('success', 'Pembayaran diproses. Status tagihan akan diperbarui setelah Midtrans mengonfirmasi.');
    }

    protected function tenant(): Tenant
    {
        $tenantId = current_tenant_id();
        abort_unless($tenantId, 403);

        return Tenant::query()->findOrFail($tenantId);
    }
}
