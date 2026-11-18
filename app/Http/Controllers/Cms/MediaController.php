<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MediaController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', CmsMedia::class);

        return view('cms.media.index', [
            'media' => CmsMedia::query()->orderByDesc('id')->paginate(24),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CmsMedia::class);

        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,gif'],
            'alt' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');
        $mime = (string) $file->getMimeType();
        abort_unless(str_starts_with($mime, 'image/'), 422);

        $name = Str::uuid()->toString().'.'.strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $path = $file->storeAs('cms/media', $name, 'public');
        $size = @getimagesize($file->getRealPath()) ?: [null, null];

        CmsMedia::query()->create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $mime,
            'size' => (int) $file->getSize(),
            'width' => $size[0] ?? null,
            'height' => $size[1] ?? null,
            'alt' => $request->input('alt'),
            'caption' => $request->input('caption'),
            'uploaded_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Media diunggah.');
    }

    public function destroy(CmsMedia $medium): RedirectResponse
    {
        $this->authorize('delete', $medium);
        $medium->delete();

        return back()->with('success', 'Media dihapus dari katalog (file fisik dapat dibersihkan kemudian).');
    }
}
