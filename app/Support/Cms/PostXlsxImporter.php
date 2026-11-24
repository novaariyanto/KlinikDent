<?php

namespace App\Support\Cms;

use App\Enums\CmsPostStatus;
use App\Models\CmsAuthor;
use App\Models\CmsCategory;
use App\Models\CmsMedia;
use App\Models\CmsPost;
use App\Models\CmsTag;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

final class PostXlsxImporter
{
    public const BUNDLED_FILENAME = 'klinikdent_29_artikel_seo.xlsx';

    /**
     * @var array<string, string>
     */
    private const HEADER_MAP = [
        'judul' => 'title',
        'title' => 'title',
        'slug' => 'slug',
        'ringkasan' => 'excerpt',
        'excerpt' => 'excerpt',
        'isi artikel (html)' => 'body',
        'isi artikel' => 'body',
        'body' => 'body',
        'svg' => 'svg',
        'faq json' => 'faq_json',
        'faq' => 'faq_json',
        'status' => 'status',
        'publish at' => 'published_at',
        'published at' => 'published_at',
        'scheduled at' => 'scheduled_at',
        'perlu review klinis' => 'needs_clinical_review',
        'kategori' => 'category',
        'category' => 'category',
        'penulis' => 'author',
        'author' => 'author',
        'reviewer' => 'reviewer',
        'tags' => 'tags',
        'seo title' => 'seo_title',
        'meta description' => 'meta_description',
        'primary keyword' => 'primary_keyword',
        'secondary keywords' => 'secondary_keywords',
        'canonical url' => 'canonical_url',
        'og title' => 'og_title',
        'og description' => 'og_description',
        'og image path' => 'og_image_path',
        'featured media id' => 'featured_media_id',
        'robots' => 'robots',
        'cta label' => 'cta_label',
        'cta url' => 'cta_url',
    ];

    public function __construct(private readonly PostService $posts) {}

    public function bundledPath(): string
    {
        return public_path(self::BUNDLED_FILENAME);
    }

    /**
     * @return list<array{row: int, title: string, status: string, message: string}>
     */
    public function importPath(string $path, User $actor, bool $overwrite = false): array
    {
        $results = [];

        foreach ($this->parse($path) as $item) {
            $results[] = $this->importOne($item['row'], $item['data'], $actor, $overwrite);
        }

        return $results;
    }

    /**
     * @return list<array{row: int, data: array<string, mixed>}>
     */
    public function parse(string $path): array
    {
        if (! is_file($path)) {
            throw ValidationException::withMessages([
                'file' => 'File XLSX tidak ditemukan.',
            ]);
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'file' => 'File XLSX tidak dapat dibaca.',
            ]);
        }

        $sheet = $spreadsheet->getSheetByName('Artikel CMS') ?? $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => 'File XLSX kosong.',
            ]);
        }

        $headers = [];
        foreach ($rows[0] ?? [] as $index => $header) {
            $key = $this->headerKey((string) $header);
            if ($key !== '') {
                $headers[$index] = $key;
            }
        }

        if (! in_array('title', $headers, true)) {
            throw ValidationException::withMessages([
                'file' => 'Kolom Judul tidak ditemukan. Gunakan berkas SEO artikel KlinikDent.',
            ]);
        }

        $parsed = [];

        foreach (array_slice($rows, 1) as $index => $row) {
            $mapped = [];
            foreach ($headers as $column => $key) {
                $mapped[$key] = is_array($row) ? ($row[$column] ?? null) : null;
            }

            $title = trim((string) ($mapped['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $parsed[] = [
                'row' => $index + 2,
                'data' => $mapped,
            ];
        }

        if ($parsed === []) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada baris artikel pada file.',
            ]);
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array{row: int, title: string, status: string, message: string}
     */
    protected function importOne(int $row, array $raw, User $actor, bool $overwrite): array
    {
        $title = Str::limit(trim((string) ($raw['title'] ?? '')), 220, '');

        try {
            $payload = $this->payload($raw, $title);
            $requestedSlug = Str::slug((string) ($payload['slug'] ?: $title)) ?: 'artikel';
            $existing = CmsPost::withTrashed()->where('slug', $requestedSlug)->first();

            if ($existing && ! $overwrite) {
                return $this->result($row, $title, 'skipped', $existing->trashed()
                    ? 'Slug sudah dipakai artikel yang dihapus.'
                    : 'Slug sudah ada.');
            }

            $guardStatus = (string) $payload['status'];
            if (in_array($guardStatus, [CmsPostStatus::Published->value, CmsPostStatus::Scheduled->value], true)) {
                $hit = EditorialGuard::findIn((string) $payload['body']);
                if ($hit !== null) {
                    return $this->result($row, $title, 'failed', 'Masih berisi teks editorial: '.$hit);
                }
            }

            $writtenImage = $this->storeSvg(
                (string) ($raw['svg'] ?? ''),
                (string) ($payload['og_image_path'] ?? ''),
                (string) $payload['slug']
            );
            if ($writtenImage !== null) {
                $payload['og_image_path'] = $writtenImage;
            }

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $post = $this->posts->update($existing, $payload, $actor, false);
                $message = 'Artikel diperbarui.';
            } else {
                $post = $this->posts->create($payload, $actor);
                $message = $post->slug !== $requestedSlug
                    ? 'Artikel dibuat dengan slug '.$post->slug.'.'
                    : 'Artikel dibuat.';
            }

            return $this->result($row, $title, $existing ? 'updated' : 'created', $message);
        } catch (\Throwable $e) {
            report($e);

            return $this->result($row, $title, 'failed', 'Gagal menyimpan artikel.');
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    protected function payload(array $raw, string $title): array
    {
        $slug = Str::slug(trim((string) ($raw['slug'] ?? ''))) ?: Str::slug($title);
        $body = preg_replace('/<h1\b[^>]*>.*?<\/h1>/is', '', (string) ($raw['body'] ?? '')) ?? '';

        return [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $this->nullableString($raw['excerpt'] ?? null, 500),
            'body' => trim($body),
            'status' => $this->statusValue($raw['status'] ?? null),
            'content_type' => 'article',
            'published_at' => $this->dateValue($raw['published_at'] ?? null),
            'scheduled_at' => $this->dateValue($raw['scheduled_at'] ?? null),
            'category_id' => $this->categoryId($raw['category'] ?? null),
            'author_id' => $this->authorId($raw['author'] ?? null),
            'reviewer_id' => $this->authorId($raw['reviewer'] ?? null),
            'featured_media_id' => $this->mediaId($raw['featured_media_id'] ?? null),
            'seo_title' => $this->nullableString($raw['seo_title'] ?? null, 180),
            'meta_description' => $this->nullableString($raw['meta_description'] ?? null, 320),
            'canonical_url' => $this->canonicalUrl($raw['canonical_url'] ?? null),
            'og_title' => $this->nullableString($raw['og_title'] ?? null, 180),
            'og_description' => $this->nullableString($raw['og_description'] ?? null, 320),
            'og_image_path' => $this->imagePath($raw['og_image_path'] ?? null),
            'robots' => $this->nullableString($raw['robots'] ?? null, 80),
            'primary_keyword' => $this->nullableString($raw['primary_keyword'] ?? null, 120),
            'secondary_keywords' => $this->nullableString($raw['secondary_keywords'] ?? null, 2000),
            'faq' => $this->faq($raw['faq_json'] ?? null),
            'cta_label' => $this->nullableString($raw['cta_label'] ?? null, 120),
            'cta_url' => $this->nullableString($raw['cta_url'] ?? null, 255),
            'needs_clinical_review' => $this->truthy($raw['needs_clinical_review'] ?? null),
            'tag_ids' => $this->tagIds($raw['tags'] ?? null),
        ];
    }

    protected function headerKey(string $header): string
    {
        $key = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $header) ?? ''));

        return self::HEADER_MAP[$key] ?? '';
    }

    protected function statusValue(mixed $value): string
    {
        $status = mb_strtolower(trim((string) $value));

        return match ($status) {
            'published', 'publish', 'terbit', 'dipublikasikan' => CmsPostStatus::Published->value,
            'scheduled', 'terjadwal', 'jadwal' => CmsPostStatus::Scheduled->value,
            'archived', 'arsip' => CmsPostStatus::Archived->value,
            'private', 'privat' => CmsPostStatus::Private->value,
            default => CmsPostStatus::Draft->value,
        };
    }

    protected function truthy(mixed $value): bool
    {
        $text = mb_strtolower(trim((string) $value));

        return in_array($text, ['1', 'ya', 'yes', 'true', 'y'], true);
    }

    protected function nullableString(mixed $value, int $limit): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : Str::limit($text, $limit, '');
    }

    protected function dateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d H:i:s');
            }

            return Carbon::parse((string) $value)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Canonical /artikel/{slug} pada berkas adalah usulan. URL blog yang dipakai aplikasi.
     */
    protected function canonicalUrl(mixed $value): ?string
    {
        $url = trim((string) $value);
        if ($url === '') {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        if (preg_match('#/artikel/[^/]+/?$#', (string) $path)) {
            return null;
        }

        return Str::limit($url, 500, '');
    }

    protected function imagePath(mixed $value): ?string
    {
        $path = trim((string) $value);
        if ($path !== '' && ! str_contains($path, '..') && preg_match('#^/images/articles/[a-z0-9\-]+\.svg$#', $path)) {
            return $path;
        }

        return null;
    }

    protected function storeSvg(string $svg, string $publicPath, string $slug): ?string
    {
        $svg = trim($svg);
        if ($svg === '' || ! str_contains(strtolower($svg), '<svg')) {
            return $publicPath !== '' ? $publicPath : null;
        }

        if (! preg_match('#^/images/articles/([a-z0-9\-]+\.svg)$#', $publicPath, $match)) {
            $safeSlug = trim(Str::limit(Str::slug($slug), 80, ''), '-');
            if ($safeSlug === '') {
                return null;
            }
            $publicPath = '/images/articles/'.$safeSlug.'.svg';
            $match = [1 => $safeSlug.'.svg'];
        }

        $svg = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $svg) ?? $svg;
        $svg = preg_replace('/\son\w+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $svg) ?? $svg;

        $dir = public_path('images/articles');
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return null;
        }

        file_put_contents($dir.DIRECTORY_SEPARATOR.$match[1], $svg);

        return $publicPath;
    }

    protected function mediaId(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 && CmsMedia::query()->whereKey($id)->exists() ? $id : null;
    }

    protected function categoryId(mixed $value): ?int
    {
        $name = trim((string) $value);
        if ($name === '') {
            return null;
        }

        $name = Str::limit($name, 120, '');
        $slug = Str::limit(Str::slug($name), 160, '');
        $category = CmsCategory::query()
            ->where('slug', $slug)
            ->orWhere('name', $name)
            ->first();

        if (! $category) {
            $category = CmsCategory::query()->create([
                'name' => $name,
                'slug' => $this->uniqueSlug(CmsCategory::class, $slug !== '' ? $slug : 'kategori'),
                'description' => 'Artikel seputar '.$name.'.',
                'is_active' => true,
                'sort_order' => ((int) CmsCategory::query()->max('sort_order')) + 1,
            ]);
        }

        return $category->id;
    }

    protected function authorId(mixed $value): ?int
    {
        $name = trim((string) $value);
        if ($name === '') {
            return null;
        }

        $name = Str::limit($name, 160, '');
        $slug = Str::limit(Str::slug($name), 180, '');
        $author = CmsAuthor::query()
            ->where('slug', $slug)
            ->orWhere('name', $name)
            ->first();

        if (! $author) {
            $author = CmsAuthor::query()->create([
                'name' => $name,
                'slug' => $this->uniqueSlug(CmsAuthor::class, $slug !== '' ? $slug : 'penulis'),
                'role_title' => 'Kontributor',
                'is_active' => true,
            ]);
        }

        return $author->id;
    }

    /**
     * @return list<int>
     */
    protected function tagIds(mixed $value): array
    {
        $ids = [];

        foreach (preg_split('/\s*,\s*/', (string) $value) ?: [] as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $name = Str::limit($name, 80, '');
            $slug = trim(Str::limit(Str::slug($name), 100, ''), '-');
            $slug = $slug !== '' ? $slug : 'tag';
            $tag = CmsTag::query()->where('slug', $slug)->orWhere('name', $name)->first();

            if (! $tag) {
                $tag = CmsTag::query()->create([
                    'name' => $name,
                    'slug' => $this->uniqueSlug(CmsTag::class, $slug),
                ]);
            }

            $ids[] = $tag->id;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<array{q: string, a: string}>|null
     */
    protected function faq(mixed $value): ?array
    {
        $json = trim((string) $value);
        if ($json === '') {
            return null;
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return null;
        }

        $faq = [];
        foreach ($decoded as $item) {
            if (! is_array($item)) {
                continue;
            }
            $question = trim((string) ($item['q'] ?? $item['question'] ?? ''));
            $answer = trim((string) ($item['a'] ?? $item['answer'] ?? ''));
            if ($question === '' && $answer === '') {
                continue;
            }
            $faq[] = ['q' => $question, 'a' => $answer];
        }

        return $faq === [] ? null : $faq;
    }

    /**
     * @param  class-string<Model>  $model
     */
    protected function uniqueSlug(string $model, string $base): string
    {
        $candidate = $base;
        $i = 2;

        while ($model::query()->where('slug', $candidate)->exists()) {
            $suffix = '-'.$i;
            $candidate = Str::limit($base, 100 - strlen($suffix), '').$suffix;
            $i++;
        }

        return $candidate;
    }

    /**
     * @return array{row: int, title: string, status: string, message: string}
     */
    protected function result(int $row, string $title, string $status, string $message): array
    {
        return [
            'row' => $row,
            'title' => $title,
            'status' => $status,
            'message' => $message,
        ];
    }
}
