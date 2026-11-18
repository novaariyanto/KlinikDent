<?php

namespace App\Http\Controllers\Cms;

use App\Enums\CmsPostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cms\StoreCmsPostRequest;
use App\Models\CmsAuthor;
use App\Models\CmsCategory;
use App\Models\CmsPost;
use App\Models\CmsPostRevision;
use App\Models\CmsTag;
use App\Support\Cms\PostService;
use App\Support\Cms\SeoAnalyzer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PostController extends Controller
{
    public function __construct(private readonly PostService $posts) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CmsPost::class);

        $items = CmsPost::query()
            ->with(['category', 'author'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($inner) => $inner->where('title', 'like', $term)->orWhere('slug', 'like', $term));
            })
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('cms.posts.index', [
            'posts' => $items,
            'statuses' => CmsPostStatus::cases(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', CmsPost::class);

        return view('cms.posts.form', $this->formData(new CmsPost([
            'status' => CmsPostStatus::Draft,
            'content_type' => 'article',
        ])));
    }

    public function store(StoreCmsPostRequest $request): RedirectResponse
    {
        $data = $this->payload($request);
        $post = $this->posts->create($data, $request->user());
        activity_log('created', $post, ['title' => $post->title], 'Artikel CMS dibuat.', 'cms');

        return redirect()->route('cms.posts.edit', $post)->with('success', 'Artikel disimpan sebagai '.$post->status->label().'.');
    }

    public function edit(CmsPost $post): View
    {
        $this->authorize('update', $post);
        $post->load(['tags', 'revisions.editor']);

        return view('cms.posts.form', $this->formData($post) + [
            'analysis' => SeoAnalyzer::analyze($post),
        ]);
    }

    public function update(StoreCmsPostRequest $request, CmsPost $post): RedirectResponse
    {
        $this->authorize('update', $post);
        $data = $this->payload($request);
        $post = $this->posts->update($post, $data, $request->user(), (bool) $request->boolean('auto_redirect', true));
        activity_log('updated', $post, ['title' => $post->title], 'Artikel CMS diperbarui.', 'cms');

        return redirect()->route('cms.posts.edit', $post)->with('success', 'Artikel diperbarui.');
    }

    public function destroy(CmsPost $post): RedirectResponse
    {
        $this->authorize('delete', $post);
        $title = $post->title;
        $post->delete();
        activity_log('deleted', $post, [], 'Artikel CMS dihapus: '.$title, 'cms');

        return redirect()->route('cms.posts.index')->with('success', 'Artikel dihapus (soft delete).');
    }

    public function duplicate(CmsPost $post): RedirectResponse
    {
        $this->authorize('create', CmsPost::class);
        $copy = $this->posts->duplicate($post, request()->user());

        return redirect()->route('cms.posts.edit', $copy)->with('success', 'Salinan artikel dibuat.');
    }

    public function restoreRevision(CmsPost $post, CmsPostRevision $revision): RedirectResponse
    {
        $this->authorize('update', $post);
        abort_unless((int) $revision->post_id === (int) $post->id, 404);
        $this->posts->restoreRevision($post, $revision, request()->user());

        return back()->with('success', 'Revisi v'.$revision->version.' dipulihkan.');
    }

    public function preview(CmsPost $post): View
    {
        $this->authorize('view', $post);

        return view('marketing.blog.show', [
            'post' => $post->load(['category', 'author', 'reviewer', 'featuredMedia', 'tags']),
            'related' => collect(),
            'toc' => $post->tableOfContents(),
            'packages' => collect(),
            'hubs' => [],
            'featureGroups' => [],
            'faqs' => [],
            'softwareFeatures' => [],
            'seo' => \App\Support\Marketing\SeoMeta::defaults(),
            'appName' => config('app.name'),
            'appLogo' => '/marketing/favicon.svg',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(CmsPost $post): array
    {
        return [
            'post' => $post,
            'categories' => CmsCategory::query()->orderBy('name')->get(),
            'authors' => CmsAuthor::query()->where('is_active', true)->orderBy('name')->get(),
            'tags' => CmsTag::query()->orderBy('name')->get(),
            'statuses' => CmsPostStatus::cases(),
            'analysis' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(StoreCmsPostRequest $request): array
    {
        $data = $request->validated();
        $data['needs_clinical_review'] = $request->boolean('needs_clinical_review');
        if (! empty($data['faq_json'])) {
            $decoded = json_decode((string) $data['faq_json'], true);
            $data['faq'] = is_array($decoded) ? $decoded : null;
        }
        unset($data['faq_json'], $data['auto_redirect']);

        return $data;
    }
}
