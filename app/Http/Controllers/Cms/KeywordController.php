<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsKeyword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class KeywordController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('cms.view'), 403);

        return view('cms.keywords.index', [
            'keywords' => CmsKeyword::query()->orderBy('pillar')->orderBy('keyword')->paginate(40),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('cms.manage'), 403);
        $data = $request->validate([
            'keyword' => ['required', 'string', 'max:160'],
            'pillar' => ['nullable', 'string', 'max:80'],
            'intent' => ['nullable', 'string', 'max:40'],
            'priority' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:30'],
            'target_url' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['slug'] = Str::slug($data['keyword']);
        $data['status'] = $data['status'] ?? 'planned';
        $data['priority'] = $data['priority'] ?? 'normal';
        CmsKeyword::query()->updateOrCreate(['slug' => $data['slug']], $data);

        return back()->with('success', 'Keyword disimpan.');
    }
}
