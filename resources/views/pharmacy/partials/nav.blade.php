@php
    $items = [
        ['route' => 'pharmacy.stock.index', 'label' => 'Stok', 'permission' => 'stock.view', 'active' => ['pharmacy.stock.index', 'pharmacy.stock.batches', 'pharmacy.stock.expired', 'pharmacy.stock.adjustments']],
        ['route' => 'pharmacy.stock.card', 'label' => 'Kartu Stok', 'permission' => 'stock.view', 'active' => ['pharmacy.stock.card', 'pharmacy.stock.card.show']],
        ['route' => 'pharmacy.stock.opname', 'label' => 'Opname', 'permission' => 'stock.manage', 'active' => ['pharmacy.stock.opname']],
        ['route' => 'pharmacy.stock.returns', 'label' => 'Retur', 'permission' => 'stock.manage', 'active' => ['pharmacy.stock.returns']],
        ['route' => 'pharmacy.lab.index', 'label' => 'Lab Gigi', 'permission' => 'pharmacy.view', 'also' => 'procedure.view', 'active' => ['pharmacy.lab.index']],
    ];
@endphp

<ul class="nav nav-tabs nav-tabs-custom mb-3">
    @foreach ($items as $item)
        @if (auth()->user()?->can($item['permission']) || (isset($item['also']) && auth()->user()?->can($item['also'])))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs(...$item['active']) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                    {{ $item['label'] }}
                </a>
            </li>
        @endif
    @endforeach
</ul>
