<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\CmsAnalyticsEvent;
use App\Models\CmsAuthor;
use App\Models\CmsCategory;
use App\Models\CmsPost;
use App\Models\CmsTag;
use App\Models\Setting;
use App\Support\Marketing\MarketingCatalog;
use App\Support\Marketing\SeoMeta;
use App\Support\Saas\ClinicProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(Request $request, ClinicProvisioningService $provisioning): View
    {
        $posts = CmsPost::query()
            ->published()
            ->with(['category', 'author', 'featuredMedia', 'tags'])
            ->when($request->filled('kategori'), function ($q) use ($request) {
                $q->whereHas('category', fn ($c) => $c->where('slug', $request->string('kategori')));
            })
            ->orderByDesc('published_at')
            ->paginate(9)
            ->withQueryString();

        return view('marketing.blog.index', [
            ...$this->base($provisioning),
            'posts' => $posts,
            'categories' => CmsCategory::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'popular' => CmsPost::query()->published()->orderByDesc('view_count')->limit(5)->get(),
        ]);
    }

    public function show(string $slug, ClinicProvisioningService $provisioning): View
    {
        $post = CmsPost::query()
            ->published()
            ->with(['category', 'author', 'reviewer', 'featuredMedia', 'tags'])
            ->where('slug', $slug)
            ->firstOrFail();

        $post->increment('view_count');
        $this->track('blog_view', $post, request());

        $related = CmsPost::query()
            ->published()
            ->where('id', '!=', $post->id)
            ->when($post->category_id, fn ($q) => $q->where('category_id', $post->category_id))
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('marketing.blog.show', [
            ...$this->base($provisioning),
            'post' => $post,
            'related' => $related,
            'toc' => $post->tableOfContents(),
        ]);
    }

    public function category(string $slug, ClinicProvisioningService $provisioning): View
    {
        $category = CmsCategory::query()->where('is_active', true)->where('slug', $slug)->firstOrFail();
        $posts = CmsPost::query()
            ->published()
            ->where('category_id', $category->id)
            ->with(['author', 'featuredMedia'])
            ->orderByDesc('published_at')
            ->paginate(9);

        return view('marketing.blog.category', [
            ...$this->base($provisioning),
            'category' => $category,
            'posts' => $posts,
        ]);
    }

    public function tag(string $slug, ClinicProvisioningService $provisioning): View
    {
        $tag = CmsTag::query()->where('slug', $slug)->firstOrFail();
        $posts = $tag->posts()
            ->published()
            ->with(['category', 'author', 'featuredMedia'])
            ->orderByDesc('published_at')
            ->paginate(9);

        return view('marketing.blog.tag', [
            ...$this->base($provisioning),
            'tag' => $tag,
            'posts' => $posts,
        ]);
    }

    public function author(string $slug, ClinicProvisioningService $provisioning): View
    {
        $author = CmsAuthor::query()->where('is_active', true)->where('slug', $slug)->firstOrFail();
        $posts = CmsPost::query()
            ->published()
            ->where('author_id', $author->id)
            ->with(['category', 'featuredMedia'])
            ->orderByDesc('published_at')
            ->paginate(9);

        return view('marketing.blog.author', [
            ...$this->base($provisioning),
            'author' => $author,
            'posts' => $posts,
        ]);
    }

    public function search(Request $request, ClinicProvisioningService $provisioning): View
    {
        $q = trim((string) $request->query('q', ''));
        $posts = CmsPost::query()
            ->published()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', '%'.$q.'%')
                        ->orWhere('excerpt', 'like', '%'.$q.'%')
                        ->orWhere('primary_keyword', 'like', '%'.$q.'%');
                });
            })
            ->with(['category', 'author'])
            ->orderByDesc('published_at')
            ->paginate(9)
            ->withQueryString();

        if ($q !== '') {
            $this->track('blog_search', null, $request, $q);
        }

        return view('marketing.blog.search', [
            ...$this->base($provisioning),
            'posts' => $posts,
            'q' => $q,
        ]);
    }

    public function rss(): Response
    {
        $posts = CmsPost::query()->published()->with('author')->orderByDesc('published_at')->limit(30)->get();

        return response()
            ->view('marketing.blog.rss', ['posts' => $posts, 'appName' => SeoMeta::get('site_name', config('app.name'))])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    public function trackCta(Request $request): Response
    {
        $post = null;
        if ($request->filled('post_id')) {
            $post = CmsPost::query()->find($request->integer('post_id'));
        }
        $this->track('cta_click', $post, $request, (string) $request->input('meta', 'blog_cta'));

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    protected function base(ClinicProvisioningService $provisioning): array
    {
        try {
            $appName = (string) Setting::getValue('app_name', config('app.name'));
        } catch (\Throwable) {
            $appName = (string) config('app.name');
        }

        return [
            'packages' => collect(),
            'hubs' => MarketingCatalog::hubs(),
            'featureGroups' => MarketingCatalog::featureGroups(),
            'faqs' => MarketingCatalog::faqs($appName),
            'softwareFeatures' => MarketingCatalog::softwareFeatures(),
            'seo' => SeoMeta::defaults(),
        ];
    }

    protected function track(string $event, ?CmsPost $post, Request $request, ?string $meta = null): void
    {
        try {
            CmsAnalyticsEvent::query()->create([
                'event' => $event,
                'post_id' => $post?->id,
                'path' => '/'.ltrim($request->path(), '/'),
                'referrer' => substr((string) $request->headers->get('referer'), 0, 255) ?: null,
                'meta' => $meta ? substr($meta, 0, 255) : null,
                'session_hash' => hash('sha256', $request->ip().'|'.(string) $request->userAgent()),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Analytics must never break page render.
        }
    }
}
