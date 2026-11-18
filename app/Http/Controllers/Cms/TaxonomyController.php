<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsAuthor;
use App\Models\CmsCategory;
use App\Models\CmsTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TaxonomyController extends Controller
{
    public function categories(): View
    {
        abort_unless(auth()->user()?->can('cms.view'), 403);

        return view('cms.taxonomies.categories', [
            'categories' => CmsCategory::query()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('cms.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string'],
            'seo_title' => ['nullable', 'string', 'max:180'],
            'meta_description' => ['nullable', 'string', 'max:320'],
        ]);
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        $data['is_active'] = true;
        CmsCategory::query()->create($data);

        return back()->with('success', 'Kategori disimpan.');
    }

    public function tags(): View
    {
        abort_unless(auth()->user()?->can('cms.view'), 403);

        return view('cms.taxonomies.tags', [
            'tags' => CmsTag::query()->orderBy('name')->get(),
        ]);
    }

    public function storeTag(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('cms.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['nullable', 'string', 'max:100'],
        ]);
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        CmsTag::query()->create($data);

        return back()->with('success', 'Tag disimpan.');
    }

    public function authors(): View
    {
        abort_unless(auth()->user()?->can('cms.view'), 403);

        return view('cms.taxonomies.authors', [
            'authors' => CmsAuthor::query()->orderBy('name')->get(),
        ]);
    }

    public function storeAuthor(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('cms.manage'), 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180'],
            'role_title' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string'],
        ]);
        $data['slug'] = Str::slug($data['slug'] ?: $data['name']);
        $data['is_active'] = true;
        CmsAuthor::query()->create($data);

        return back()->with('success', 'Penulis disimpan.');
    }
}
