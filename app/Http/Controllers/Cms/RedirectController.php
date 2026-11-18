<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\SeoRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RedirectController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', SeoRedirect::class);

        return view('cms.seo.redirects', [
            'redirects' => SeoRedirect::query()->orderByDesc('id')->paginate(30),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', SeoRedirect::class);

        $data = $request->validate([
            'source_path' => ['required', 'string', 'max:255'],
            'destination_url' => ['required', 'string', 'max:500'],
            'status_code' => ['required', 'in:301,302'],
        ]);

        $source = '/'.ltrim(parse_url($data['source_path'], PHP_URL_PATH) ?: $data['source_path'], '/');
        $destination = $data['destination_url'];
        if (! str_starts_with($destination, '/') && ! str_starts_with($destination, 'http')) {
            return back()->with('error', 'Destination harus path internal atau URL absolut.');
        }

        SeoRedirect::query()->updateOrCreate(
            ['source_path' => $source],
            [
                'destination_url' => $destination,
                'status_code' => (int) $data['status_code'],
                'is_active' => true,
                'created_by' => $request->user()->id,
            ]
        );

        return back()->with('success', 'Redirect disimpan.');
    }

    public function destroy(SeoRedirect $redirect): RedirectResponse
    {
        $this->authorize('delete', $redirect);
        $redirect->delete();

        return back()->with('success', 'Redirect dihapus.');
    }
}
