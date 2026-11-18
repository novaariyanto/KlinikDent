<?php

namespace App\Support\Cms;

use App\Enums\CmsPostStatus;
use App\Models\CmsPost;
use App\Models\CmsPostRevision;
use App\Models\SeoRedirect;
use App\Models\User;
use Illuminate\Support\Str;

final class PostService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): CmsPost
    {
        $data = $this->normalize($data, $actor);
        $post = CmsPost::query()->create($data);
        $this->syncTags($post, $data['tag_ids'] ?? []);
        $this->snapshot($post, $actor, 'Revisi awal');

        return $post->fresh(['tags', 'category', 'author', 'featuredMedia']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CmsPost $post, array $data, User $actor, bool $autoRedirect = true): CmsPost
    {
        $oldSlug = $post->slug;
        $wasPublished = $post->isPubliclyVisible();
        $data = $this->normalize($data, $actor, $post);
        $post->update($data);
        $this->syncTags($post, $data['tag_ids'] ?? null);
        $this->snapshot($post, $actor, 'Perubahan konten');

        if ($autoRedirect && $wasPublished && $oldSlug !== $post->slug) {
            SeoRedirect::query()->updateOrCreate(
                ['source_path' => '/blog/'.$oldSlug],
                [
                    'destination_url' => '/blog/'.$post->slug,
                    'status_code' => 301,
                    'is_active' => true,
                    'created_by' => $actor->id,
                ]
            );
        }

        return $post->fresh(['tags', 'category', 'author', 'featuredMedia']);
    }

    public function duplicate(CmsPost $post, User $actor): CmsPost
    {
        $copy = $post->replicate(['slug', 'status', 'published_at', 'scheduled_at', 'view_count']);
        $copy->title = $post->title.' (Salinan)';
        $copy->slug = $this->uniqueSlug(Str::slug($copy->title));
        $copy->status = CmsPostStatus::Draft;
        $copy->published_at = null;
        $copy->scheduled_at = null;
        $copy->created_by = $actor->id;
        $copy->updated_by = $actor->id;
        $copy->save();
        $copy->tags()->sync($post->tags()->pluck('cms_tags.id')->all());
        $this->snapshot($copy, $actor, 'Duplikasi dari #'.$post->id);

        return $copy;
    }

    public function restoreRevision(CmsPost $post, CmsPostRevision $revision, User $actor): CmsPost
    {
        $snapshot = $revision->snapshot ?? [];
        unset($snapshot['id'], $snapshot['created_at'], $snapshot['updated_at'], $snapshot['deleted_at']);
        $tagIds = $snapshot['tag_ids'] ?? [];
        unset($snapshot['tag_ids']);
        $snapshot['updated_by'] = $actor->id;
        $post->fill($snapshot);
        $post->body = HtmlSanitizer::clean($post->body);
        $post->save();
        if (is_array($tagIds)) {
            $post->tags()->sync($tagIds);
        }
        $this->snapshot($post, $actor, 'Restore revisi v'.$revision->version);

        return $post->fresh(['tags']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalize(array $data, User $actor, ?CmsPost $existing = null): array
    {
        $data['title'] = trim((string) ($data['title'] ?? ''));
        $data['slug'] = $this->uniqueSlug(
            filled($data['slug'] ?? null) ? Str::slug((string) $data['slug']) : Str::slug($data['title']),
            $existing?->id
        );
        $data['body'] = HtmlSanitizer::clean($data['body'] ?? null);
        $data['excerpt'] = isset($data['excerpt']) ? Str::limit(trim(strip_tags((string) $data['excerpt'])), 500, '') : null;
        $data['updated_by'] = $actor->id;
        if (! $existing) {
            $data['created_by'] = $actor->id;
        }

        $status = CmsPostStatus::tryFrom((string) ($data['status'] ?? 'draft')) ?? CmsPostStatus::Draft;
        $data['status'] = $status->value;

        if ($status === CmsPostStatus::Published && empty($data['published_at'])) {
            $data['published_at'] = now();
        }
        if ($status === CmsPostStatus::Scheduled && ! empty($data['scheduled_at'])) {
            $data['published_at'] = $data['scheduled_at'];
        }

        $data['reading_time_minutes'] = max(1, (int) ceil(str_word_count(strip_tags((string) $data['body'])) / 200));
        $data['needs_clinical_review'] = (bool) ($data['needs_clinical_review'] ?? false);

        if (isset($data['secondary_keywords']) && is_string($data['secondary_keywords'])) {
            $data['secondary_keywords'] = array_values(array_filter(array_map('trim', explode(',', $data['secondary_keywords']))));
        }

        return $data;
    }

    protected function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = $slug !== '' ? $slug : 'artikel';
        $candidate = $base;
        $i = 2;
        while (
            CmsPost::query()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $candidate)
                ->exists()
        ) {
            $candidate = $base.'-'.$i;
            $i++;
        }

        return $candidate;
    }

    /**
     * @param  list<int|string>|null  $tagIds
     */
    protected function syncTags(CmsPost $post, ?array $tagIds): void
    {
        if ($tagIds === null) {
            return;
        }

        $post->tags()->sync(array_map('intval', $tagIds));
    }

    protected function snapshot(CmsPost $post, User $actor, string $summary): void
    {
        $version = (int) $post->revisions()->max('version') + 1;
        $snapshot = $post->only([
            'title', 'slug', 'excerpt', 'body', 'status', 'content_type', 'published_at', 'scheduled_at',
            'category_id', 'author_id', 'reviewer_id', 'featured_media_id', 'seo_title', 'meta_description',
            'canonical_url', 'og_title', 'og_description', 'og_image_path', 'robots', 'primary_keyword',
            'secondary_keywords', 'faq', 'related_post_ids', 'cta_label', 'cta_url', 'needs_clinical_review',
        ]);
        $snapshot['tag_ids'] = $post->tags()->pluck('cms_tags.id')->all();
        $snapshot['status'] = $post->status instanceof CmsPostStatus ? $post->status->value : (string) $post->status;

        CmsPostRevision::query()->create([
            'post_id' => $post->id,
            'version' => $version,
            'edited_by' => $actor->id,
            'summary' => $summary,
            'status' => $snapshot['status'],
            'snapshot' => $snapshot,
        ]);
    }
}
