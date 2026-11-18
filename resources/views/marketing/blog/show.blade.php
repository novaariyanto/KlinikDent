@extends('marketing.layout')

@section('title', $post->seoTitleResolved())
@section('og_title', $post->og_title ?: $post->title)
@section('description', $post->metaDescriptionResolved())
@section('canonical', $post->canonicalResolved())
@section('og_type', 'article')
@section('og_image', $post->og_image_path ? url($post->og_image_path) : ($post->featuredMedia?->url() ?: ''))
@section('robots', $post->robots ?: 'index,follow')

@push('head')
@php
    $blogPosting = [
        '@type' => 'BlogPosting',
        'headline' => $post->title,
        'description' => $post->metaDescriptionResolved(),
        'mainEntityOfPage' => $post->canonicalResolved(),
        'publisher' => [
            '@type' => 'Organization',
            'name' => $appName,
            'logo' => ['@type' => 'ImageObject', 'url' => url($appLogo)],
        ],
        'keywords' => collect([$post->primary_keyword])->merge($post->secondary_keywords ?? [])->filter()->values()->all(),
    ];
    if ($post->published_at) {
        $blogPosting['datePublished'] = $post->published_at->toAtomString();
    }
    if ($post->updated_at) {
        $blogPosting['dateModified'] = $post->updated_at->toAtomString();
    }
    if ($post->author) {
        $blogPosting['author'] = [
            '@type' => 'Person',
            'name' => $post->author->name,
            'url' => route('blog.author', $post->author->slug),
        ];
    }
    if ($post->featuredMedia?->url()) {
        $blogPosting['image'] = $post->featuredMedia->url();
    } elseif ($post->og_image_path) {
        $blogPosting['image'] = url($post->og_image_path);
    }

    $blogGraph = [
        $blogPosting,
        \App\Support\Marketing\SeoMeta::breadcrumbList([
            ['name' => 'Beranda', 'item' => url('/')],
            ['name' => 'Blog', 'item' => route('blog.index')],
            ['name' => $post->title, 'item' => $post->canonicalResolved()],
        ]),
    ];
    if (! empty($post->faq)) {
        $blogGraph[] = [
            '@type' => 'FAQPage',
            'mainEntity' => collect($post->faq)->map(fn ($item) => [
                '@type' => 'Question',
                'name' => $item['q'] ?? '',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $item['a'] ?? '',
                ],
            ])->values()->all(),
        ];
    }
@endphp
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@graph' => $blogGraph,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@section('content')
<section class="section">
    <div class="wrap feature-hero">
        <article class="prose">
            <p class="micro">
                <a href="{{ route('blog.index') }}">Blog</a>
                @if ($post->category)
                    · <a href="{{ route('blog.category', $post->category->slug) }}">{{ $post->category->name }}</a>
                @endif
                · {{ optional($post->published_at)->format('d M Y') }}
                @if ($post->updated_at && $post->published_at && $post->updated_at->gt($post->published_at->copy()->addDay()))
                    · Diperbarui {{ $post->updated_at->format('d M Y') }}
                @endif
                · {{ $post->reading_time_minutes ?: $post->estimateReadingMinutes() }} menit baca
            </p>
            <h1>{{ $post->title }}</h1>
            @if ($post->author)
                <p class="micro">Oleh <a href="{{ route('blog.author', $post->author->slug) }}">{{ $post->author->name }}</a>
                    @if ($post->author->role_title) · {{ $post->author->role_title }} @endif
                </p>
            @endif
            @if ($post->featuredMedia)
                <img
                    class="article-hero-img"
                    src="{{ $post->featuredMedia->url() }}"
                    alt="{{ $post->featuredMedia->alt ?: $post->title }}"
                    width="1200"
                    height="675"
                    loading="eager"
                    decoding="async"
                >
            @endif
            @if ($post->excerpt)
                <p class="lede">{{ $post->excerpt }}</p>
            @endif
            <div class="article-body">
                {!! $post->bodyWithTocAnchors() !!}
            </div>

            @if (! empty($post->faq))
                <h2>FAQ</h2>
                @foreach ($post->faq as $item)
                    <h3>{{ $item['q'] ?? '' }}</h3>
                    <p>{{ $item['a'] ?? '' }}</p>
                @endforeach
            @endif

            @if ($post->tags->isNotEmpty())
                <p class="micro">Tag:
                    @foreach ($post->tags as $tag)
                        <a href="{{ route('blog.tag', $tag->slug) }}">{{ $tag->name }}</a>@if (! $loop->last), @endif
                    @endforeach
                </p>
            @endif

            <div class="share-row">
                <a class="btn btn--ghost" href="https://wa.me/?text={{ urlencode($post->title.' '.$post->canonicalResolved()) }}" target="_blank" rel="noopener">Bagikan WhatsApp</a>
                <a class="btn btn--ghost" href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode($post->canonicalResolved()) }}" target="_blank" rel="noopener">Bagikan LinkedIn</a>
            </div>
        </article>

        <aside class="article-aside">
            @if (! empty($toc))
                <div class="card toc-card">
                    <h2 class="aside-title">Daftar isi</h2>
                    <ul class="toc-list">
                        @foreach ($toc as $item)
                            <li class="{{ ($item['level'] ?? 2) > 2 ? 'toc-list--nested' : '' }}"><a href="#{{ $item['id'] }}">{{ $item['text'] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="card">
                <h2 class="aside-title">{{ $post->cta_label ?: 'Kelola Klinik Gigi Lebih Mudah dengan KlinikDent' }}</h2>
                <p>Rekam medis, odontogram digital, dan operasional klinik dalam satu sistem.</p>
                <a class="btn btn--primary" href="{{ $post->cta_url ?: route('register') }}" data-track="blog_cta" data-post-id="{{ $post->id }}">Coba KlinikDent Gratis</a>
            </div>
        </aside>
    </div>
</section>

@if ($related->isNotEmpty())
<section class="section section--soft">
    <div class="wrap">
        <h2 class="section-title">Artikel terkait</h2>
        <div class="cards">
            @foreach ($related as $item)
                <article class="card blog-card">
                    <div class="blog-card__body">
                        <h3>{{ $item->title }}</h3>
                        <a href="{{ route('blog.show', $item->slug) }}">Baca</a>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
@endif

@include('marketing.partials.cta-band')
@endsection
