<?php

namespace App\Http\Controllers\Cms;

use App\Enums\CmsPostStatus;
use App\Http\Controllers\Controller;
use App\Models\CmsAnalyticsEvent;
use App\Models\CmsPost;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('cms.view'), 403);

        $posts = CmsPost::query();

        return view('cms.dashboard', [
            'total' => (clone $posts)->count(),
            'published' => (clone $posts)->where('status', CmsPostStatus::Published)->count(),
            'draft' => (clone $posts)->where('status', CmsPostStatus::Draft)->count(),
            'scheduled' => (clone $posts)->where('status', CmsPostStatus::Scheduled)->count(),
            'missingMeta' => CmsPost::query()->where(fn ($q) => $q->whereNull('meta_description')->orWhere('meta_description', ''))->count(),
            'missingImage' => CmsPost::query()->whereNull('featured_media_id')->where(fn ($q) => $q->whereNull('og_image_path')->orWhere('og_image_path', ''))->count(),
            'needsUpdate' => CmsPost::query()->published()->where('updated_at', '<', now()->subMonths(6))->count(),
            'topPosts' => CmsPost::query()->orderByDesc('view_count')->limit(8)->get(['id', 'title', 'slug', 'view_count', 'status']),
            'ctaClicks' => CmsAnalyticsEvent::query()->where('event', 'cta_click')->where('created_at', '>=', now()->subDays(30))->count(),
            'blogViews' => CmsAnalyticsEvent::query()->where('event', 'blog_view')->where('created_at', '>=', now()->subDays(30))->count(),
            'gscStatus' => 'belum_terhubung',
        ]);
    }
}
