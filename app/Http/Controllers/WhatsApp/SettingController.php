<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Requests\WhatsApp\UpdateSettingRequest;
use App\Models\WhatsAppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingController extends WhatsAppController
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('whatsapp.settings.manage') || auth()->user()?->can('whatsapp.view'), 403);

        $tenantId = $this->provision();
        $setting = WhatsAppSetting::forTenant($tenantId);

        return view('whatsapp.settings', [
            'setting' => $setting,
            'canManage' => (bool) auth()->user()?->can('whatsapp.settings.manage'),
        ]);
    }

    public function update(UpdateSettingRequest $request): RedirectResponse
    {
        $tenantId = $this->provision();
        $setting = WhatsAppSetting::forTenant($tenantId);
        $data = $request->validated();

        $setting->enabled = (bool) ($data['enabled'] ?? false);
        $setting->api_base_url = $data['api_base_url'] ?: config('services.whatsapp.base_url');
        $setting->phone_number = $data['phone_number'] ?? null;
        $setting->business_name = $data['business_name'] ?? null;
        $setting->default_language = $data['default_language'] ?? 'id';

        if (filled($data['api_key'] ?? null)) {
            $setting->api_key = $data['api_key'];
        }

        $setting->save();

        return back()->with('success', 'Pengaturan WhatsApp disimpan.');
    }
}
