<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsCategory;
use App\Models\CmsContentPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContentPlanController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('cms.view'), 403);

        return view('cms.plans.index', [
            'plans' => CmsContentPlan::query()->with(['category', 'assignee'])->orderByDesc('id')->paginate(20),
            'categories' => CmsCategory::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('cms.manage'), 403);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:220'],
            'target_keyword' => ['nullable', 'string', 'max:160'],
            'search_intent' => ['nullable', 'string', 'max:40'],
            'category_id' => ['nullable', 'integer', 'exists:cms_categories,id'],
            'target_publish_date' => ['nullable', 'date'],
            'status' => ['required', 'string', 'max:30'],
            'priority' => ['nullable', 'string', 'max:20'],
            'outline' => ['nullable', 'string'],
            'editor_notes' => ['nullable', 'string'],
        ]);
        $data['assignee_id'] = $request->user()->id;
        CmsContentPlan::query()->create($data);

        return back()->with('success', 'Rencana konten ditambahkan.');
    }
}
