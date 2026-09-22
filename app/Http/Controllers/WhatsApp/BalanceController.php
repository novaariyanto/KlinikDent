<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Requests\WhatsApp\CheckTopupRequest;
use App\Http\Requests\WhatsApp\StoreTopupRequest;
use App\Models\WhatsAppSetting;
use App\Support\WhatsApp\WhatsAppBalanceService;
use App\Support\WhatsApp\WhatsAppException;
use App\Support\WhatsApp\WhatsAppPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BalanceController extends WhatsAppController
{
    public function index(WhatsAppBalanceService $balance): View
    {
        abort_unless(auth()->user()?->can('whatsapp.view'), 403);

        $tenantId = $this->provision();
        $setting = WhatsAppSetting::forTenant($tenantId);
        $account = null;
        $channels = [];
        $error = null;

        if ($setting->isReady()) {
            try {
                $account = $balance->account($tenantId);
            } catch (WhatsAppException $exception) {
                $error = $exception->getMessage();
            }

            try {
                $channels = $balance->channels($tenantId);
            } catch (WhatsAppException $exception) {
                $error ??= $exception->getMessage();
            }
        }

        $topup = session(WhatsAppBalanceService::SESSION_KEY);

        return view('whatsapp.balance', [
            'setting' => $setting,
            'account' => $account,
            'channels' => $channels,
            'error' => $error,
            'topup' => is_array($topup) ? $topup : null,
            'canManage' => (bool) auth()->user()?->can('whatsapp.settings.manage'),
            'defaultPhone' => WhatsAppPhone::toLocal($setting->phone_number),
        ]);
    }

    public function store(StoreTopupRequest $request, WhatsAppBalanceService $balance): RedirectResponse
    {
        try {
            $link = $balance->createLink($this->tenantId(), $request->validated());
        } catch (WhatsAppException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        session([WhatsAppBalanceService::SESSION_KEY => $link]);

        return redirect()
            ->route('whatsapp.balance')
            ->with('success', 'Link pembayaran berhasil dibuat. Buka URL pembayaran untuk menyelesaikan topup.');
    }

    public function check(CheckTopupRequest $request, WhatsAppBalanceService $balance): RedirectResponse
    {
        $orderId = (string) $request->validated('order_id');

        try {
            $link = $balance->link($this->tenantId(), $orderId);
        } catch (WhatsAppException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        session([WhatsAppBalanceService::SESSION_KEY => $link]);

        if ($balance->isPaid($link)) {
            return redirect()
                ->route('whatsapp.balance')
                ->with('success', 'Pembayaran berhasil. Saldo bertambah otomatis dari callback gateway.');
        }

        return redirect()
            ->route('whatsapp.balance')
            ->with('info', 'Status pembayaran: '.$link['status']);
    }
}
