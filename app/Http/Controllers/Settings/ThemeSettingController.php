<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Setting\UpdateThemeSettingRequest;
use App\Support\ClinicTheme;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ThemeSettingController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->check(), 403);

        return view('settings.theme', [
            'presets' => ClinicTheme::presets(),
            'theme' => ClinicTheme::current(),
            'canManage' => true,
        ]);
    }

    public function update(UpdateThemeSettingRequest $request): JsonResponse|RedirectResponse
    {
        abort_unless(auth()->check(), 403);

        $preset = (string) $request->validated('theme_preset');

        ClinicTheme::remember(
            $preset,
            $request->validated('theme_primary'),
            $request->validated('theme_navy'),
            $request->validated('theme_accent'),
        );

        $message = 'Tema tampilan Anda disimpan untuk sesi ini.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'style' => ClinicTheme::style(),
                'theme' => ClinicTheme::current(),
            ]);
        }

        return back()->with('success', $message);
    }
}
