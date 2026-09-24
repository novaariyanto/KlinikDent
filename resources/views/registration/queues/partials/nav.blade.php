@php
    $items = [
        ['route' => 'queue.today', 'label' => 'Antrean', 'active' => ['queue.index', 'queue.today', 'queue.mine', 'queue.waiting', 'queue.called']],
        ['route' => 'queue.loket', 'label' => 'Loket', 'active' => ['queue.loket']],
        ['route' => 'queue.monitor', 'label' => 'Monitor', 'active' => ['queue.monitor']],
    ];
@endphp

@can('viewAny', App\Models\Queue::class)
    <ul class="nav nav-tabs nav-tabs-custom mb-3">
        @foreach ($items as $item)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs(...$item['active']) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                    {{ $item['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
@endcan
