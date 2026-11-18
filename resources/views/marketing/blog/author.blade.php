@extends('marketing.layout')

@section('title', 'Profil '.$author->name.' | Blog KlinikDent')
@section('description', $author->bio ?: ('Artikel oleh '.$author->name))
@section('canonical', route('blog.author', $author->slug))

@section('content')
<section class="hero hero--page"><div class="wrap">
    <h1>{{ $author->name }}</h1>
    @if ($author->role_title)<p class="eyebrow">{{ $author->role_title }}</p>@endif
    <p class="lede">{{ $author->bio }}</p>
</div></section>
<section class="section"><div class="wrap">
    <div class="cards">
        @forelse ($posts as $post)
            <article class="card"><h2 style="font-size:1.1rem">{{ $post->title }}</h2><a href="{{ route('blog.show', $post->slug) }}">Baca</a></article>
        @empty
            <p>Belum ada artikel published.</p>
        @endforelse
    </div>
    {{ $posts->links('marketing.pagination') }}
</div></section>
@endsection
