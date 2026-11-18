@push('head')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'WebPage',
            'name' => $page['title'],
            'description' => $page['description'],
            'url' => $canonical,
            'isPartOf' => ['@id' => url('/').'#website'],
            'inLanguage' => 'id-ID',
        ],
        \App\Support\Marketing\SeoMeta::breadcrumbList([
            ['name' => 'Beranda', 'item' => url('/')],
            ['name' => $page['h1'], 'item' => $canonical],
        ]),
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush
