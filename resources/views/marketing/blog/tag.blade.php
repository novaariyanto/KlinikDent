@extends('marketing.layout')

@section('title', 'Tag '.$tag->name.' | Blog KlinikDent')
@section('description', 'Artikel dengan tag '.$tag->name.' di blog KlinikDent.')
@section('canonical', route('blog.tag', $tag->slug))

@section('content')
<section class="hero hero--page hero--splash">
    <div class="wrap">
        <p class="eyebrow">Tag blog</p>
        <h1>Tag: {{ $tag->name }}</h1>
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
                <p>Belum ada artikel.</p>
            @endforelse
        </div>
        {{ $posts->links('marketing.pagination') }}
    </div>
</section>
@endsection
