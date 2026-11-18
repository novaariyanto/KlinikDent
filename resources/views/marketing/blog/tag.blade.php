@extends('marketing.layout')

@section('title', 'Tag '.$tag->name.' | Blog KlinikDent')
@section('description', 'Artikel dengan tag '.$tag->name.' di blog KlinikDent.')
@section('canonical', route('blog.tag', $tag->slug))

@section('content')
<section class="hero hero--page"><div class="wrap"><h1>Tag: {{ $tag->name }}</h1></div></section>
<section class="section"><div class="wrap">
    <div class="cards">
        @forelse ($posts as $post)
            <article class="card"><h2 style="font-size:1.1rem">{{ $post->title }}</h2><a href="{{ route('blog.show', $post->slug) }}">Baca</a></article>
        @empty
            <p>Belum ada artikel.</p>
        @endforelse
    </div>
    {{ $posts->links() }}
</div></section>
@endsection
