<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\SeoSetting;
use App\Support\Marketing\SeoMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SeoSettingsController extends Controller
{
    public function edit(): View
    {
        abort_unless(auth()->user()?->can('seo.manage'), 403);

        return view('cms.seo.settings', [
            'settings' => SeoMeta::defaults(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('seo.manage'), 403);

        $data = $request->validate([
            'site_name' => ['nullable', 'string', 'max:120'],
            'site_description' => ['nullable', 'string', 'max:500'],
            'default_seo_title' => ['nullable', 'string', 'max:180'],
            'default_meta_description' => ['nullable', 'string', 'max:320'],
            'default_og_image' => ['nullable', 'string', 'max:255'],
            'canonical_base' => ['nullable', 'url', 'max:255'],
            'google_verification' => ['nullable', 'string', 'max:120'],
            'bing_verification' => ['nullable', 'string', 'max:120'],
            'ga4_id' => ['nullable', 'string', 'max:40'],
            'gtm_id' => ['nullable', 'string', 'max:40'],
            'organization_name' => ['nullable', 'string', 'max:160'],
            'organization_description' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($data as $key => $value) {
            SeoSetting::put($key, $value);
        }

        return back()->with('success', 'Pengaturan SEO disimpan.');
    }
}
