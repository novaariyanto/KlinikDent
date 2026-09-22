<?php

namespace App\Http\Controllers\Saas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Saas\UpdatePaymentGatewayRequest;
use App\Support\Saas\PaymentGatewaySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentGatewayController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('payment_gateway.view'), 403);

        return view('saas.payment-gateway.index', [
            'settings' => PaymentGatewaySettings::current(),
            'canManage' => (bool) auth()->user()?->can('payment_gateway.manage'),
            'notificationUrl' => route('midtrans.notification'),
            'finishUrl' => route('subscription.invoices.finish'),
        ]);
    }

    public function update(UpdatePaymentGatewayRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['enabled'] = $request->boolean('enabled');

        if ($data['enabled'] && ! filled($data['server_key'] ?? null) && ! PaymentGatewaySettings::current()['has_server_key']) {
            return back()->withInput()->withErrors([
                'server_key' => 'Server Key wajib diisi jika Midtrans diaktifkan.',
            ]);
        }

        PaymentGatewaySettings::save($data);

        activity_log('updated', null, [
            'enabled' => $data['enabled'],
            'environment' => $data['environment'],
            'merchant_id' => $data['merchant_id'] ?? null,
        ], 'Pengaturan payment gateway Midtrans disimpan.', 'saas');

        return back()->with('success', 'Pengaturan payment gateway disimpan.');
    }
}
