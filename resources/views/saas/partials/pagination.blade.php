@if ($paginator->hasPages())
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mt-3">
        <p class="text-muted small mb-0">
            Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ number_format($paginator->total(), 0, ',', '.') }}
        </p>
        <nav aria-label="Paginasi aktivitas">
            <ul class="pagination pagination-rounded mb-0">
                <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
                    @if ($paginator->onFirstPage())
                        <span class="page-link" aria-hidden="true"><i class="bx bx-chevron-left"></i></span>
                    @else
                        <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Sebelumnya"><i class="bx bx-chevron-left"></i></a>
                    @endif
                </li>

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li class="page-item disabled" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                            @else
                                <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                <li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
                    @if ($paginator->hasMorePages())
                        <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Berikutnya"><i class="bx bx-chevron-right"></i></a>
                    @else
                        <span class="page-link" aria-hidden="true"><i class="bx bx-chevron-right"></i></span>
                    @endif
                </li>
            </ul>
        </nav>
    </div>
@endif
