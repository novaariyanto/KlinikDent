<?php

namespace App\Http\Controllers\Owner;

use App\Enums\AddonStatus;
use App\Enums\RoleName;
use App\Enums\SaasInvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\SaasAddon;
use App\Models\SaasAddonInvoice;
use App\Models\Tenant;
use App\Support\Saas\AddonBillingService;
use App\Support\Saas\AddonService;
use App\Support\Saas\MidtransException;
use App\Support\Saas\MidtransService;
use App\Support\Saas\PaymentGatewaySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AddonMarketplaceController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('addon.view'), 403);

        $tenant = $this->tenant();
        $addons = SaasAddon::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $subscriptions = $tenant->addonSubscriptions()
            ->with('addon')
            ->get()
            ->keyBy('addon_id');

        return view('addons.index', [
            'addons' => $addons,
            'subscriptions' => $subscriptions,
            'isOwner' => (bool) auth()->user()?->hasRole(RoleName::Owner),
        ]);
    }

    public function show(SaasAddon $saasAddon): View
    {
        abort_unless(auth()->user()?->can('addon.view'), 403);
        abort_unless($saasAddon->is_active, 404);

        $tenant = $this->tenant();
        $subscription = $tenant->addonSubscriptions()
            ->where('addon_id', $saasAddon->id)
            ->first();

        $unpaidInvoice = $subscription
            ? $subscription->invoices()->where('status', SaasInvoiceStatus::Unpaid)->latest('id')->first()
            : null;

        return view('addons.show', [
            'addon' => $saasAddon,
            'subscription' => $subscription,
            'unpaidInvoice' => $unpaidInvoice,
            'isOwner' => (bool) auth()->user()?->hasRole(RoleName::Owner),
            'midtransReady' => PaymentGatewaySettings::isReady(),
        ]);
    }

    public function activate(SaasAddon $saasAddon, AddonService $addons): RedirectResponse
    {
        abort_unless(auth()->user()?->can('addon.view'), 403);
        abort_unless(auth()->user()?->hasRole(RoleName::Owner), 403);
        abort_unless($saasAddon->is_active, 404);

        $trial = (int) $saasAddon->trial_days > 0;

        if (! $saasAddon->isFree() && ! $trial) {
            return redirect()
                ->route('addons.show', $saasAddon)
                ->with('error', 'Add-on berbayar. Gunakan Bayar & Aktifkan.');
        }

        $subscription = $addons->ensureSubscription($this->tenant(), $saasAddon);
        $addons->activate($subscription, auth()->user(), $trial);

        activity_log(
            'updated',
            $subscription->fresh(),
            ['status' => AddonStatus::Active->value],
            'Add-on '.$saasAddon->name.' diaktifkan.',
            'addon'
        );

        return redirect()
            ->route('addons.show', $saasAddon)
            ->with('success', 'Add-on '.$saasAddon->name.' berhasil diaktifkan.');
    }

    public function pay(
        SaasAddon $saasAddon,
        AddonService $addons,
        AddonBillingService $billing,
        MidtransService $midtrans,
    ): RedirectResponse {
        abort_unless(auth()->user()?->can('addon.view'), 403);
        abort_unless(auth()->user()?->hasRole(RoleName::Owner), 403);
        abort_unless($saasAddon->is_active, 404);
        abort_unless(! $saasAddon->isFree(), 422, 'Add-on gratis tidak memerlukan pembayaran.');

        $user = auth()->user();
        $subscription = $addons->ensureSubscription($this->tenant(), $saasAddon);

        if ($subscription->status === AddonStatus::Active) {
            return redirect()
                ->route('addons.show', $saasAddon)
                ->with('success', 'Add-on sudah aktif.');
        }

        $unpaid = $subscription->invoices()
            ->where('status', SaasInvoiceStatus::Unpaid)
            ->latest('id')
            ->first();

        $invoice = $unpaid ?? $billing->issueInvoice($subscription, $saasAddon->price);

        try {
            $midtrans->createAddonSnapToken($invoice, $user);
        } catch (MidtransException $exception) {
            return redirect()
                ->route('addons.show', $saasAddon)
                ->with('error', $exception->getMessage());
        }

        return redirect()->route('addons.checkout', $invoice);
    }

    public function checkout(SaasAddonInvoice $saasAddonInvoice): View|RedirectResponse
    {
        abort_unless(auth()->user()?->can('addon.view'), 403);
        abort_unless(auth()->user()?->hasRole(RoleName::Owner), 403);
        abort_unless((int) $saasAddonInvoice->tenant_id === (int) $this->tenant()->id, 403);

        if ($saasAddonInvoice->status !== SaasInvoiceStatus::Unpaid || ! $saasAddonInvoice->midtrans_snap_token) {
            return redirect()
                ->route('addons.index')
                ->with('error', 'Sesi pembayaran tidak tersedia. Silakan bayar ulang.');
        }

        return view('addons.checkout', [
            'invoice' => $saasAddonInvoice->load('addonSubscription.addon'),
            'snapToken' => $saasAddonInvoice->midtrans_snap_token,
            'clientKey' => PaymentGatewaySettings::clientKey(),
            'snapScriptUrl' => PaymentGatewaySettings::snapScriptUrl(),
            'finishUrl' => route('addons.invoices.finish', ['order_id' => $saasAddonInvoice->midtrans_order_id]),
            'addonsUrl' => route('addons.index'),
        ]);
    }

    public function finish(Request $request, MidtransService $midtrans): RedirectResponse
    {
        abort_unless(auth()->user()?->can('addon.view'), 403);

        $orderId = (string) $request->query('order_id', '');
        $invoice = $midtrans->syncAddonByOrderId($orderId, (int) $this->tenant()->id);

        if ($invoice && $invoice->status === SaasInvoiceStatus::Paid) {
            return redirect()
                ->route('addons.index')
                ->with('success', 'Pembayaran berhasil. Add-on aktif.');
        }

        return redirect()
            ->route('addons.index')
            ->with('success', 'Pembayaran diproses. Status akan diperbarui setelah Midtrans mengonfirmasi.');
    }

    protected function tenant(): Tenant
    {
        $tenantId = current_tenant_id();
        abort_unless($tenantId, 403);

        return Tenant::query()->findOrFail($tenantId);
    }
}
