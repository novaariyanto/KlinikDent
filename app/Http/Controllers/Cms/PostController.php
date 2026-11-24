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
use App\Support\Cms\PostXlsxImporter;
use App\Support\Cms\SeoAnalyzer;
use App\Support\Marketing\SeoMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class PostController extends Controller
{
    public function __construct(private readonly PostService $posts) {}

    public function index(): View
    {
        $this->authorize('viewAny', CmsPost::class);

        $columns = [
            ['data' => 'checkbox', 'name' => 'checkbox', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '40px'],
            ['data' => 'DT_RowIndex', 'name' => 'DT_RowIndex', 'title' => 'No', 'orderable' => false, 'searchable' => false, 'className' => 'text-center', 'width' => '50px'],
            ['data' => 'title', 'name' => 'title', 'title' => 'Judul'],
            ['data' => 'status_badge', 'name' => 'status', 'title' => 'Status', 'className' => 'text-center'],
            ['data' => 'category_name', 'name' => 'category.name', 'title' => 'Kategori'],
            ['data' => 'published_at', 'name' => 'published_at', 'title' => 'Publish'],
            ['data' => 'updated_at', 'name' => 'updated_at', 'title' => 'Diubah'],
            ['data' => 'action', 'name' => 'action', 'title' => 'Aksi', 'orderable' => false, 'searchable' => false, 'className' => 'text-center text-nowrap', 'width' => '140px'],
        ];

        return view('cms.posts.index', [
            'columns' => $columns,
            'statuses' => CmsPostStatus::cases(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CmsPost::class);

        $query = CmsPost::query()->with(['category', 'author']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('checkbox', function (CmsPost $post) {
                return '<input type="checkbox" class="form-check-input cms-post-check" value="'.$post->id.'" aria-label="Pilih artikel">';
            })
            ->editColumn('title', function (CmsPost $post) {
                $title = e($post->title);
                $slug = e($post->slug);

                return '<a href="'.route('cms.posts.edit', $post).'">'.$title.'</a>'
                    .'<div class="small text-muted">/blog/'.$slug.'</div>';
            })
            ->addColumn('status_badge', function (CmsPost $post) {
                $label = e($post->status?->label() ?? (string) $post->status);
                $class = match ($post->status) {
                    CmsPostStatus::Published => 'bg-success',
                    CmsPostStatus::Scheduled => 'bg-info',
                    CmsPostStatus::Archived => 'bg-secondary',
                    CmsPostStatus::Private => 'bg-dark',
                    default => 'bg-warning text-dark',
                };

                return '<span class="badge '.$class.'">'.$label.'</span>';
            })
            ->addColumn('category_name', fn (CmsPost $post) => e($post->category?->name ?: '—'))
            ->editColumn('published_at', fn (CmsPost $post) => $post->published_at?->format('d M Y H:i') ?: '—')
            ->editColumn('updated_at', fn (CmsPost $post) => $post->updated_at?->format('d M Y H:i') ?: '—')
            ->addColumn('action', fn (CmsPost $post) => view('cms.posts.partials.actions', compact('post'))->render())
            ->filterColumn('category.name', function ($query, $keyword) {
                $query->whereHas('category', fn ($q) => $q->where('name', 'like', '%'.$keyword.'%'));
            })
            ->rawColumns(['checkbox', 'title', 'status_badge', 'action'])
            ->toJson();
    }

    public function import(Request $request, PostXlsxImporter $importer): JsonResponse
    {
        $this->authorize('create', CmsPost::class);

        $user = $request->user();
        abort_unless($user !== null, 403);

        if ($request->boolean('bundled')) {
            $path = $importer->bundledPath();
        } else {
            $request->validate([
                'file' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls'],
            ], [
                'file.required' => 'Pilih file XLSX artikel.',
                'file.mimes' => 'File harus berformat XLSX.',
                'file.max' => 'Ukuran file maksimal 10 MB.',
            ]);
            $path = (string) $request->file('file')?->getRealPath();
        }

        $results = $importer->importPath($path, $user, $request->boolean('overwrite'));
        $created = count(array_filter($results, fn (array $row) => $row['status'] === 'created'));
        $updated = count(array_filter($results, fn (array $row) => $row['status'] === 'updated'));
        $skipped = count(array_filter($results, fn (array $row) => $row['status'] === 'skipped'));
        $failed = count(array_filter($results, fn (array $row) => $row['status'] === 'failed'));

        activity_log(
            'created',
            null,
            ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'failed' => $failed],
            'Impor artikel CMS: '.$created.' baru, '.$updated.' diperbarui, '.$skipped.' dilewati, '.$failed.' gagal.',
            'cms'
        );

        return response()->json([
            'ok' => $failed === 0,
            'message' => 'Impor selesai. Ditambah '.$created.', diperbarui '.$updated.', dilewati '.$skipped.', gagal '.$failed.'.',
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'results' => $results,
        ]);
    }

    public function bulkStatus(Request $request): JsonResponse
    {
        $this->authorize('create', CmsPost::class);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:cms_posts,id'],
            'status' => ['required', 'string'],
        ]);

        $status = CmsPostStatus::tryFrom($data['status']);
        if (! $status) {
            return response()->json(['message' => 'Status tidak valid.'], 422);
        }

        $posts = CmsPost::query()->whereIn('id', $data['ids'])->get();
        $updated = 0;

        foreach ($posts as $post) {
            $payload = ['status' => $status->value];
            if ($status === CmsPostStatus::Published && ! $post->published_at) {
                $payload['published_at'] = now();
            }
            if ($status === CmsPostStatus::Scheduled && $post->scheduled_at && ! $post->published_at) {
                $payload['published_at'] = $post->scheduled_at;
            }
            $post->update($payload);
            $updated++;
        }

        activity_log(
            'updated',
            null,
            ['ids' => $data['ids'], 'status' => $status->value, 'count' => $updated],
            'Ubah status massal '.$updated.' artikel CMS menjadi '.$status->label().'.',
            'cms'
        );

        return response()->json([
            'ok' => true,
            'message' => $updated.' artikel diubah menjadi '.$status->label().'.',
            'updated' => $updated,
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
            'seo' => SeoMeta::defaults(),
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
