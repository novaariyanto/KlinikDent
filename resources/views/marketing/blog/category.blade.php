@extends('marketing.layout')

@section('title', ($category->seo_title ?: $category->name.' - Artikel Klinik Gigi').' | KlinikDent')
@section('description', $category->meta_description ?: ('Kumpulan artikel '.$category->name.' di blog KlinikDent.'))
@section('canonical', route('blog.category', $category->slug))

@section('content')
<section class="hero hero--page"><div class="wrap"><h1>{{ $category->name }}</h1><p class="lede">{{ $category->description }}</p></div></section>
<section class="section"><div class="wrap">
    <div class="cards">
        @forelse ($posts as $post)
            <article class="card"><h2 style="font-size:1.1rem">{{ $post->title }}</h2><a href="{{ route('blog.show', $post->slug) }}">Baca</a></article>
        @empty
            <p>Belum ada artikel pada kategori ini.</p>
        @endforelse
    </div>
    {{ $posts->links() }}
</div></section>
@endsection
