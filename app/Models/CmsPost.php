<?php

namespace App\Models;

use App\Enums\CmsPostStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CmsPost extends Model
{
    use SoftDeletes;

    protected $table = 'cms_posts';

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'body',
        'status',
        'content_type',
        'published_at',
        'scheduled_at',
        'category_id',
        'author_id',
        'reviewer_id',
        'featured_media_id',
        'seo_title',
        'meta_description',
        'canonical_url',
        'og_title',
        'og_description',
        'og_image_path',
        'robots',
        'primary_keyword',
        'secondary_keywords',
        'faq',
        'related_post_ids',
        'cta_label',
        'cta_url',
        'needs_clinical_review',
        'reading_time_minutes',
        'view_count',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => CmsPostStatus::class,
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'secondary_keywords' => 'array',
            'faq' => 'array',
            'related_post_ids' => 'array',
            'needs_clinical_review' => 'boolean',
            'reading_time_minutes' => 'integer',
            'view_count' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CmsCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(CmsAuthor::class, 'author_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(CmsAuthor::class, 'reviewer_id');
    }

    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(CmsMedia::class, 'featured_media_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(CmsTag::class, 'cms_post_tag', 'post_id', 'tag_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(CmsPostRevision::class, 'post_id')->orderByDesc('version');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', CmsPostStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === CmsPostStatus::Published
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    public function seoTitleResolved(): string
    {
        return $this->seo_title ?: $this->title.' | KlinikDent';
    }

    public function metaDescriptionResolved(): string
    {
        if (filled($this->meta_description)) {
            return (string) $this->meta_description;
        }

        return Str::limit(strip_tags((string) ($this->excerpt ?: $this->body)), 155);
    }

    public function canonicalResolved(): string
    {
        return $this->canonical_url ?: route('blog.show', $this->slug);
    }

    public function estimateReadingMinutes(): int
    {
        $words = str_word_count(strip_tags((string) $this->body));

        return max(1, (int) ceil($words / 200));
    }

    /**
     * @return list<array{id: string, text: string}>
     */
    public function tableOfContents(): array
    {
        $toc = [];
        if (! preg_match_all('/<h([2-3])[^>]*>(.*?)<\/h\1>/is', (string) $this->body, $matches, PREG_SET_ORDER)) {
            return $toc;
        }

        foreach ($matches as $index => $match) {
            $text = trim(html_entity_decode(strip_tags($match[2])));
            if ($text === '') {
                continue;
            }
            $toc[] = [
                'id' => 'section-'.($index + 1),
                'text' => $text,
                'level' => (int) $match[1],
            ];
        }

        return $toc;
    }

    public function bodyWithTocAnchors(): string
    {
        $index = 0;

        return preg_replace_callback('/<h([2-3])([^>]*)>(.*?)<\/h\1>/is', function ($match) use (&$index) {
            $index++;
            $id = 'section-'.$index;
            $attrs = $match[2];
            if (preg_match('/\sid=/i', $attrs)) {
                return $match[0];
            }

            return '<h'.$match[1].$attrs.' id="'.$id.'">'.$match[3].'</h'.$match[1].'>';
        }, (string) $this->body) ?? (string) $this->body;
    }
}
