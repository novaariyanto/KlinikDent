<?php

namespace App\Support\Cms;

use App\Models\CmsPost;

final class SeoAnalyzer
{
    /**
     * @return list<array{level: string, message: string}>
     */
    public static function analyze(CmsPost $post): array
    {
        $issues = [];
        $title = $post->seo_title ?: $post->title;
        $desc = $post->meta_description ?: '';
        $body = strip_tags((string) $post->body);
        $keyword = mb_strtolower((string) $post->primary_keyword);

        $titleLen = mb_strlen($title);
        if ($titleLen < 30 || $titleLen > 65) {
            $issues[] = ['level' => 'warn', 'message' => 'SEO title sebaiknya sekitar 30–65 karakter (saat ini '.$titleLen.').'];
        } else {
            $issues[] = ['level' => 'ok', 'message' => 'Panjang SEO title sudah baik.'];
        }

        $descLen = mb_strlen($desc);
        if ($descLen < 70 || $descLen > 160) {
            $issues[] = ['level' => 'warn', 'message' => 'Meta description sebaiknya sekitar 70–160 karakter (saat ini '.$descLen.').'];
        } else {
            $issues[] = ['level' => 'ok', 'message' => 'Panjang meta description sudah baik.'];
        }

        if ($keyword !== '') {
            if (! str_contains(mb_strtolower($title), $keyword)) {
                $issues[] = ['level' => 'warn', 'message' => 'Primary keyword belum tampak pada judul SEO.'];
            }
            $lead = mb_strtolower(mb_substr($body, 0, 400));
            if (! str_contains($lead, $keyword)) {
                $issues[] = ['level' => 'warn', 'message' => 'Primary keyword belum tampak pada paragraf awal.'];
            }
        }

        if (! preg_match('/<h2[\s>]/i', (string) $post->body)) {
            $issues[] = ['level' => 'warn', 'message' => 'Artikel belum memiliki heading H2.'];
        }

        if (! $post->featured_media_id && ! $post->og_image_path) {
            $issues[] = ['level' => 'warn', 'message' => 'Featured image / OG image belum diisi.'];
        } elseif ($post->featuredMedia && ! filled($post->featuredMedia->alt)) {
            $issues[] = ['level' => 'warn', 'message' => 'Featured image belum memiliki ALT text.'];
        }

        if (! preg_match('/href=["\']\//i', (string) $post->body) && ! preg_match('/href=["\']https?:\/\/[^"\']*klinikdent/i', (string) $post->body)) {
            $issues[] = ['level' => 'warn', 'message' => 'Artikel belum memiliki internal link yang jelas.'];
        } else {
            $issues[] = ['level' => 'ok', 'message' => 'Internal link terdeteksi pada isi artikel.'];
        }

        if (! filled($post->slug)) {
            $issues[] = ['level' => 'error', 'message' => 'Slug wajib diisi.'];
        }

        return $issues;
    }
}
