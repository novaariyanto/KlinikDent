<?php

namespace App\Http\Controllers;

use App\Http\Requests\Setting\UpdateSettingRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        return view('settings.index', [
            'settings' => [
                'app_name' => Setting::getValue('app_name', config('app.name')),
                'timezone' => Setting::getValue('timezone', config('app.timezone')),
                'records_per_page' => Setting::getValue('records_per_page', '10'),
            ],
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(UpdateSettingRequest $request): RedirectResponse
    {
        foreach ($request->validated() as $key => $value) {
            Setting::setValue($key, (string) $value);
        }

        return back()->with('success', 'Settings saved successfully.');
    }
}
