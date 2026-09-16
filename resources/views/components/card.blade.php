@props([
    'title' => null,
])

<div {{ $attributes->class(['card']) }}>
    @if ($title || isset($headerActions))
        <div class="card-header bg-transparent">
            <div class="d-flex align-items-center justify-content-between">
                @if ($title)
                    <h4 class="card-title mb-0">{{ $title }}</h4>
                @endif
                @isset($headerActions)
                    <div>{{ $headerActions }}</div>
                @endisset
            </div>
        </div>
    @endif
    <div class="card-body">
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="card-footer bg-transparent">
            {{ $footer }}
        </div>
    @endisset
</div>
