@extends('marketing.layout')

@section('title', 'Blog Klinik Gigi & Odontogram Digital | KlinikDent')
@section('og_title', 'Blog KlinikDent')
@section('description', 'Artikel tentang software klinik gigi, odontogram digital, rekam medis elektronik, dan digitalisasi klinik gigi di Indonesia.')
@section('canonical', route('blog.index'))

@section('content')
<section class="hero hero--page hero--splash">
    <div class="wrap">
        <p class="eyebrow">Blog KlinikDent</p>
        <h1>Belajar digitalisasi klinik gigi</h1>
        <p class="lede">Panduan praktis seputar software klinik gigi, odontogram digital, dan rekam medis elektronik.</p>
        <form action="{{ route('blog.search') }}" method="GET" class="blog-search">
            <label>Cari artikel
                <input type="search" name="q" placeholder="cth: odontogram digital" value="{{ request('q') }}">
            </label>
            <button class="btn btn--primary" type="submit">Cari</button>
        </form>
    </div>
</section>

<section class="section">
    <div class="wrap">
        @if ($categories->isNotEmpty())
            <nav class="category-chips" aria-label="Kategori">
                @foreach ($categories as $category)
                    <a class="btn btn--ghost" href="{{ route('blog.category', $category->slug) }}">{{ $category->name }}</a>
                @endforeach
            </nav>
        @endif

        <div class="cards">
            @forelse ($posts as $post)
                <article class="card blog-card">
                    @if ($post->featuredMedia)
                        <img
                            class="blog-card__media"
                            src="{{ $post->featuredMedia->url() }}"
                            alt="{{ $post->featuredMedia->alt ?: $post->title }}"
                            width="640"
                            height="360"
                            loading="lazy"
                            decoding="async"
                        >
                    @endif
                    <div class="blog-card__body">
                        <p class="micro">{{ optional($post->published_at)->format('d M Y') }} · {{ $post->reading_time_minutes ?: $post->estimateReadingMinutes() }} mnt baca</p>
                        <h2>{{ $post->title }}</h2>
                        <p>{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) $post->body), 140) }}</p>
                        <a href="{{ route('blog.show', $post->slug) }}">Baca selengkapnya</a>
                    </div>
                </article>
            @empty
                <p class="section-lede">Belum ada artikel published. Hub SEO KlinikDent tetap tersedia di beranda.</p>
            @endforelse
        </div>

        {{ $posts->links('marketing.pagination') }}
    </div>
</section>

@include('marketing.partials.cta-band', [
    'title' => 'Siap menerapkan digitalisasi di klinik Anda?',
    'lede' => 'Coba KlinikDent atau jadwalkan demo sesuai alur operasional klinik gigi.',
])
@endsection
