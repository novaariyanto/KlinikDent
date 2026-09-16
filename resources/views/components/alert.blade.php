@props([
    'type' => null,
    'message' => null,
    'dismissible' => true,
])

@php
    $alerts = [];

    if ($message) {
        $alerts[] = ['type' => $type ?? 'info', 'message' => $message];
    }

    foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info', 'status' => 'success'] as $key => $class) {
        if (session()->has($key) && ! $message) {
            $alerts[] = ['type' => $class, 'message' => session($key)];
        }
    }
@endphp

@foreach ($alerts as $alert)
    <div {{ $attributes->class(['alert', 'alert-'.$alert['type'], 'alert-dismissible fade show']) }} role="alert">
        {{ $alert['message'] }}
        @if ($dismissible)
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        @endif
    </div>
@endforeach

@if ($errors->any() && ! $message)
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
