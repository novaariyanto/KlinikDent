@extends('marketing.layout')

@section('title', ($category->seo_title ?: $category->name.' - Artikel Klinik Gigi').' | KlinikDent')
@section('description', $category->meta_description ?: ('Kumpulan artikel '.$category->name.' di blog KlinikDent.'))
@section('canonical', route('blog.category', $category->slug))

@section('content')
<section class="hero hero--page hero--splash">
    <div class="wrap">
        <p class="eyebrow">Kategori blog</p>
        <h1>{{ $category->name }}</h1>
        <p class="lede">{{ $category->description }}</p>
    </div>
</section>
<section class="section">
    <div class="wrap">
        <div class="cards">
            @forelse ($posts as $post)
                <article class="card blog-card">
                    <div class="blog-card__body">
                        <h2>{{ $post->title }}</h2>
                        <a href="{{ route('blog.show', $post->slug) }}">Baca</a>
                    </div>
                </article>
            @empty
                <p>Belum ada artikel pada kategori ini.</p>
            @endforelse
        </div>
        {{ $posts->links('marketing.pagination') }}
    </div>
</section>
@endsection
