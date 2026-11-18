@if ($paginator->hasPages())
    <nav class="blog-pager" role="navigation" aria-label="Navigasi halaman">
        <p class="blog-pager__meta">
            Menampilkan
            <strong>{{ $paginator->firstItem() }}</strong>–<strong>{{ $paginator->lastItem() }}</strong>
            dari <strong>{{ $paginator->total() }}</strong> artikel
        </p>

        <ul class="blog-pager__list">
            @if ($paginator->onFirstPage())
                <li><span class="blog-pager__btn is-disabled" aria-disabled="true">Sebelumnya</span></li>
            @else
                <li><a class="blog-pager__btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">Sebelumnya</a></li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="blog-pager__btn is-disabled" aria-hidden="true">…</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li><span class="blog-pager__btn is-current" aria-current="page">{{ $page }}</span></li>
                        @else
                            <li><a class="blog-pager__btn" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li><a class="blog-pager__btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya</a></li>
            @else
                <li><span class="blog-pager__btn is-disabled" aria-disabled="true">Berikutnya</span></li>
            @endif
        </ul>
    </nav>
@endif
