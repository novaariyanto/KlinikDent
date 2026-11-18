@extends('marketing.layout')

@section('title', 'Blog Klinik Gigi & Odontogram Digital | KlinikDent')
@section('og_title', 'Blog KlinikDent')
@section('description', 'Artikel tentang software klinik gigi, odontogram digital, rekam medis elektronik, dan digitalisasi klinik gigi di Indonesia.')
@section('canonical', route('blog.index'))

@section('content')
<section class="hero hero--page">
    <div class="wrap">
        <p class="eyebrow">Blog KlinikDent</p>
        <h1>Belajar Digitalisasi Klinik Gigi</h1>
        <p class="lede">Panduan praktis seputar software klinik gigi, odontogram digital, dan rekam medis elektronik.</p>
        <form action="{{ route('blog.search') }}" method="GET" class="starter-form" style="max-width:28rem;margin-top:1rem">
            <label>Cari artikel
                <input type="search" name="q" placeholder="cth: odontogram digital" value="">
            </label>
            <button class="btn btn--primary" type="submit">Cari</button>
        </form>
    </div>
</section>

<section class="section">
    <div class="wrap">
        @if ($categories->isNotEmpty())
            <nav class="trust" aria-label="Kategori" style="margin-bottom:1.5rem;flex-wrap:wrap;gap:.5rem;display:flex">
                @foreach ($categories as $category)
                    <a class="btn btn--ghost" href="{{ route('blog.category', $category->slug) }}">{{ $category->name }}</a>
                @endforeach
            </nav>
        @endif

        <div class="cards">
            @forelse ($posts as $post)
                <article class="card">
                    @if ($post->featuredMedia)
                        <img src="{{ $post->featuredMedia->url() }}" alt="{{ $post->featuredMedia->alt ?: $post->title }}" loading="lazy" style="width:100%;border-radius:.5rem;margin-bottom:.75rem">
                    @endif
                    <p class="micro">{{ optional($post->published_at)->format('d M Y') }} · {{ $post->reading_time_minutes ?: $post->estimateReadingMinutes() }} mnt baca</p>
                    <h2 style="font-size:1.15rem">{{ $post->title }}</h2>
                    <p>{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags((string) $post->body), 140) }}</p>
                    <a href="{{ route('blog.show', $post->slug) }}">Baca selengkapnya</a>
                </article>
            @empty
                <p class="section-lede">Belum ada artikel published. Hub SEO KlinikDent tetap tersedia di beranda.</p>
            @endforelse
        </div>

        <div style="margin-top:1.5rem">{{ $posts->links() }}</div>
    </div>
</section>
@endsection
