<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateClinicSettingRequest;
use App\Models\ClinicSetting;
use App\Models\Tenant;
use App\Support\ClinicSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ClinicSettingController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('setting.view'), 403);

        $tenantId = current_tenant_id();
        abort_unless($tenantId, 403);

        $tenant = Tenant::query()->findOrFail($tenantId);

        return view('settings.clinic', [
            'tenant' => $tenant,
            'settings' => ClinicSettings::current($tenantId),
            'timezones' => ClinicSettings::timezones(),
            'hasCustomLogo' => ClinicSettings::hasCustomLogo($tenantId),
            'logoUrl' => ClinicSettings::logoUrl('dark', $tenantId),
            'canManage' => (bool) auth()->user()?->can('setting.manage'),
        ]);
    }

    public function update(UpdateClinicSettingRequest $request): RedirectResponse
    {
        $tenantId = current_tenant_id();
        abort_unless($tenantId, 403);

        $data = $request->safe()->except(['logo', 'remove_logo']);

        foreach ($data as $key => $value) {
            ClinicSetting::setValue($key, $value === null ? null : (string) $value, $tenantId);
        }

        if ($request->boolean('remove_logo')) {
            $this->deleteStoredLogo($tenantId);
            ClinicSetting::setValue('logo', null, $tenantId);
        }

        if ($request->hasFile('logo')) {
            $this->deleteStoredLogo($tenantId);
            ClinicSetting::setValue(
                'logo',
                $request->file('logo')->store('clinic-logos/'.$tenantId, 'public'),
                $tenantId
            );
        }

        $tenant = Tenant::query()->find($tenantId);

        if ($tenant && $tenant->name !== $data['clinic_name']) {
            $tenant->update(['name' => $data['clinic_name']]);
        }

        activity_log('updated', $tenant, [
            'keys' => array_values(array_filter([
                ...array_keys($data),
                $request->hasFile('logo') || $request->boolean('remove_logo') ? 'logo' : null,
            ])),
        ], 'Updated clinic settings', 'clinic-settings');

        return back()->with('success', 'Pengaturan klinik berhasil disimpan.');
    }

    protected function deleteStoredLogo(int $tenantId): void
    {
        $path = ClinicSetting::getValue('logo', null, $tenantId);

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
