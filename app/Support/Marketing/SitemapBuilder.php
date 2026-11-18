<?php

namespace App\Support\Marketing;

use App\Models\CmsAuthor;
use App\Models\CmsCategory;
use App\Models\CmsPost;
use Illuminate\Support\Facades\Schema;

final class SitemapBuilder
{
    /**
     * @return list<array{loc: string, priority: string, changefreq: string, lastmod?: string}>
     */
    public static function urls(): array
    {
        $urls = [
            ['loc' => route('home'), 'priority' => '1.0', 'changefreq' => 'weekly', 'lastmod' => now()->toAtomString()],
            ['loc' => route('marketing.dokter-baru'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => route('marketing.klinik'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => route('marketing.multi-cabang'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['loc' => route('marketing.odontogram'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => route('marketing.rekam-medis'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => route('privacy'), 'priority' => '0.3', 'changefreq' => 'yearly'],
            ['loc' => route('terms'), 'priority' => '0.3', 'changefreq' => 'yearly'],
            ['loc' => route('blog.index'), 'priority' => '0.7', 'changefreq' => 'daily'],
        ];

        try {
            if (Schema::hasTable('cms_posts')) {
                CmsPost::query()->published()->orderByDesc('updated_at')->get(['slug', 'updated_at', 'published_at'])
                    ->each(function (CmsPost $post) use (&$urls) {
                        $urls[] = [
                            'loc' => route('blog.show', $post->slug),
                            'priority' => '0.6',
                            'changefreq' => 'weekly',
                            'lastmod' => optional($post->updated_at)->toAtomString(),
                        ];
                    });
            }
            if (Schema::hasTable('cms_categories')) {
                CmsCategory::query()->where('is_active', true)->get(['slug', 'updated_at'])
                    ->each(function (CmsCategory $category) use (&$urls) {
                        $urls[] = [
                            'loc' => route('blog.category', $category->slug),
                            'priority' => '0.5',
                            'changefreq' => 'weekly',
                            'lastmod' => optional($category->updated_at)->toAtomString(),
                        ];
                    });
            }
            if (Schema::hasTable('cms_authors')) {
                CmsAuthor::query()->where('is_active', true)->get(['slug', 'updated_at'])
                    ->each(function (CmsAuthor $author) use (&$urls) {
                        $urls[] = [
                            'loc' => route('blog.author', $author->slug),
                            'priority' => '0.4',
                            'changefreq' => 'monthly',
                            'lastmod' => optional($author->updated_at)->toAtomString(),
                        ];
                    });
            }
        } catch (\Throwable) {
            // Blog tables may not exist yet.
        }

        return $urls;
    }
}
