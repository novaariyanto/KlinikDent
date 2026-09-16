@props([
    'title',
    'breadcrumb' => [],
])

<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <h4 class="mb-sm-0 font-size-18">{{ $title }}</h4>
            <div class="page-title-right">
                @if ($breadcrumb)
                    <ol class="breadcrumb m-0">
                        @foreach ($breadcrumb as $label => $url)
                            @if ($loop->last || ! $url)
                                <li class="breadcrumb-item active">{{ is_int($label) ? $url : $label }}</li>
                            @else
                                <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                            @endif
                        @endforeach
                    </ol>
                @endif
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
