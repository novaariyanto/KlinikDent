@extends('layouts.app')

@section('title', $post->exists ? 'Ubah Artikel' : 'Tambah Artikel')

@section('content')
    <x-page-header :title="$post->exists ? 'Ubah Artikel' : 'Tambah Artikel'" :breadcrumb="['CMS' => route('cms.dashboard'), 'Artikel' => route('cms.posts.index'), ($post->exists ? 'Ubah' : 'Tambah') => null]" />
    <x-alert />

    <form method="POST" action="{{ $post->exists ? route('cms.posts.update', $post) : route('cms.posts.store') }}">
        @csrf
        @if ($post->exists) @method('PUT') @endif

        <div class="row">
            <div class="col-lg-8">
                <x-card title="Konten">
                    <div class="mb-3">
                        <label class="form-label">Judul</label>
                        <input class="form-control" name="title" value="{{ old('title', $post->title) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Slug</label>
                        <input class="form-control" name="slug" value="{{ old('slug', $post->slug) }}" placeholder="otomatis dari judul bila kosong">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ringkasan</label>
                        <textarea class="form-control" name="excerpt" rows="2">{{ old('excerpt', $post->excerpt) }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Isi artikel</label>
                        <div id="editor" style="min-height:280px;background:#fff">{!! old('body', $post->body) !!}</div>
                        <input type="hidden" name="body" id="body-input" value="{{ old('body', $post->body) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">FAQ JSON (opsional)</label>
                        <textarea class="form-control font-monospace" name="faq_json" rows="4" placeholder='[{"q":"...","a":"..."}]'>{{ old('faq_json', $post->faq ? json_encode($post->faq, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) : '') }}</textarea>
                    </div>
                </x-card>
            </div>
            <div class="col-lg-4">
                <x-card title="Publikasi" class="mb-3">
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            @foreach ($statuses as $status)
                                <option value="{{ $status->value }}" @selected(old('status', $post->status?->value) === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Publish at</label>
                        <input class="form-control" type="datetime-local" name="published_at" value="{{ old('published_at', optional($post->published_at)->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Scheduled at</label>
                        <input class="form-control" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', optional($post->scheduled_at)->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="needs_clinical_review" value="1" id="clinical" @checked(old('needs_clinical_review', $post->needs_clinical_review))>
                        <label class="form-check-label" for="clinical">Perlu review klinis</label>
                    </div>
                    @if ($post->exists)
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="auto_redirect" value="1" id="auto_redirect" checked>
                            <label class="form-check-label" for="auto_redirect">Buat redirect 301 jika slug berubah</label>
                        </div>
                    @endif
                    <button class="btn btn-primary w-100" type="submit">Simpan</button>
                    @if ($post->exists)
                        <a class="btn btn-soft-secondary w-100 mt-2" href="{{ route('cms.posts.preview', $post) }}" target="_blank">Preview</a>
                    @endif
                </x-card>

                <x-card title="Taksonomi" class="mb-3">
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select class="form-select" name="category_id">
                            <option value="">—</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected((int) old('category_id', $post->category_id) === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Penulis</label>
                        <select class="form-select" name="author_id">
                            <option value="">—</option>
                            @foreach ($authors as $author)
                                <option value="{{ $author->id }}" @selected((int) old('author_id', $post->author_id) === $author->id)>{{ $author->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reviewer</label>
                        <select class="form-select" name="reviewer_id">
                            <option value="">—</option>
                            @foreach ($authors as $author)
                                <option value="{{ $author->id }}" @selected((int) old('reviewer_id', $post->reviewer_id) === $author->id)>{{ $author->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tags</label>
                        <select class="form-select" name="tag_ids[]" multiple size="6">
                            @foreach ($tags as $tag)
                                <option value="{{ $tag->id }}" @selected(collect(old('tag_ids', $post->tags?->pluck('id')->all() ?? []))->contains($tag->id))>{{ $tag->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </x-card>

                <x-card title="SEO" class="mb-3">
                    <div class="mb-2"><label class="form-label">SEO title</label><input class="form-control" name="seo_title" value="{{ old('seo_title', $post->seo_title) }}"></div>
                    <div class="mb-2"><label class="form-label">Meta description</label><textarea class="form-control" name="meta_description" rows="3">{{ old('meta_description', $post->meta_description) }}</textarea></div>
                    <div class="mb-2"><label class="form-label">Primary keyword</label><input class="form-control" name="primary_keyword" value="{{ old('primary_keyword', $post->primary_keyword) }}"></div>
                    <div class="mb-2"><label class="form-label">Secondary keywords (koma)</label><input class="form-control" name="secondary_keywords" value="{{ old('secondary_keywords', is_array($post->secondary_keywords) ? implode(', ', $post->secondary_keywords) : '') }}"></div>
                    <div class="mb-2"><label class="form-label">Canonical URL</label><input class="form-control" name="canonical_url" value="{{ old('canonical_url', $post->canonical_url) }}"></div>
                    <div class="mb-2"><label class="form-label">OG title</label><input class="form-control" name="og_title" value="{{ old('og_title', $post->og_title) }}"></div>
                    <div class="mb-2"><label class="form-label">OG description</label><textarea class="form-control" name="og_description" rows="2">{{ old('og_description', $post->og_description) }}</textarea></div>
                    <div class="mb-2"><label class="form-label">OG image path</label><input class="form-control" name="og_image_path" value="{{ old('og_image_path', $post->og_image_path) }}"></div>
                    <div class="mb-2"><label class="form-label">Featured media ID</label><input class="form-control" name="featured_media_id" value="{{ old('featured_media_id', $post->featured_media_id) }}"></div>
                    <div class="mb-2"><label class="form-label">Robots</label><input class="form-control" name="robots" value="{{ old('robots', $post->robots) }}" placeholder="index,follow"></div>
                    <div class="mb-2"><label class="form-label">CTA label</label><input class="form-control" name="cta_label" value="{{ old('cta_label', $post->cta_label) }}"></div>
                    <div class="mb-2"><label class="form-label">CTA URL</label><input class="form-control" name="cta_url" value="{{ old('cta_url', $post->cta_url) }}"></div>
                </x-card>

                @if (! empty($analysis))
                    <x-card title="SEO Analysis" class="mb-3">
                        <ul class="mb-0">
                            @foreach ($analysis as $item)
                                <li class="{{ $item['level'] === 'ok' ? 'text-success' : ($item['level'] === 'error' ? 'text-danger' : 'text-warning') }}">{{ $item['message'] }}</li>
                            @endforeach
                        </ul>
                        <div class="small text-muted mt-2">Indikator lokal, bukan skor Google.</div>
                    </x-card>
                @endif

                @if ($post->exists && $post->revisions->isNotEmpty())
                    <x-card title="Revisi">
                        <ul class="mb-0">
                            @foreach ($post->revisions->take(8) as $revision)
                                <li class="mb-2">
                                    v{{ $revision->version }} · {{ $revision->created_at?->format('d M Y H:i') }}
                                    <div class="small text-muted">{{ $revision->summary }}</div>
                                    <form method="POST" action="{{ route('cms.posts.revisions.restore', [$post, $revision]) }}" class="d-inline">@csrf<button class="btn btn-sm btn-soft-primary">Restore</button></form>
                                </li>
                            @endforeach
                        </ul>
                    </x-card>
                @endif
            </div>
        </div>
    </form>
    @if ($post->exists)
        <form method="POST" action="{{ route('cms.posts.destroy', $post) }}" class="mt-2" style="max-width:20rem" onsubmit="return confirm('Hapus artikel ini?')">
            @csrf @method('DELETE')
            <button class="btn btn-soft-danger" type="submit">Soft delete artikel</button>
        </form>
    @endif
@endsection

@push('scripts')
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<script>
(function () {
    var quill = new Quill('#editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ header: [2, 3, 4, false] }],
                ['bold', 'italic', 'underline', 'blockquote', 'code-block'],
                [{ list: 'ordered' }, { list: 'bullet' }],
                ['link', 'image'],
                ['clean']
            ]
        }
    });
    var input = document.getElementById('body-input');
    var form = input.closest('form');
    form.addEventListener('submit', function () {
        input.value = quill.root.innerHTML;
    });
})();
</script>
@endpush
