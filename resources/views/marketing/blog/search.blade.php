@extends('marketing.layout')

@section('title', 'Cari Artikel'.($q ? ': '.$q : '').' | Blog KlinikDent')
@section('description', 'Hasil pencarian artikel blog KlinikDent.')
@section('canonical', route('blog.search', array_filter(['q' => $q])))
@section('robots', 'noindex,follow')

@section('content')
<section class="hero hero--page hero--splash">
    <div class="wrap">
        <p class="eyebrow">Pencarian</p>
        <h1>Cari artikel</h1>
        <form action="{{ route('blog.search') }}" method="GET" class="blog-search">
            <label>Kata kunci
                <input type="search" name="q" value="{{ $q }}">
            </label>
            <button class="btn btn--primary" type="submit">Cari</button>
        </form>
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
                <p>Tidak ada hasil untuk “{{ $q }}”.</p>
            @endforelse
        </div>
        {{ $posts->links('marketing.pagination') }}
    </div>
</section>
@endsection
