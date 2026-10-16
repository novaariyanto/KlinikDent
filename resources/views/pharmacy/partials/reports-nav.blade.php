@php
    $items = [
        ['route' => 'pharmacy.reports', 'label' => 'Ringkasan', 'active' => ['pharmacy.reports', 'reports.pharmacy']],
        ['route' => 'pharmacy.reports.stock', 'label' => 'Stok', 'active' => ['pharmacy.reports.stock']],
        ['route' => 'pharmacy.reports.incoming', 'label' => 'Obat Masuk', 'active' => ['pharmacy.reports.incoming']],
        ['route' => 'pharmacy.reports.outgoing', 'label' => 'Obat Keluar', 'active' => ['pharmacy.reports.outgoing']],
        ['route' => 'pharmacy.reports.expired', 'label' => 'Expired', 'active' => ['pharmacy.reports.expired']],
        ['route' => 'pharmacy.transactions', 'label' => 'Semua Transaksi', 'active' => ['pharmacy.transactions'], 'permission' => 'pharmacy.view'],
    ];
@endphp

<ul class="nav nav-tabs nav-tabs-custom mb-3">
    @foreach ($items as $item)
        @php
            $allowed = ! isset($item['permission'])
                || auth()->user()?->can($item['permission'])
                || auth()->user()?->can('report.view')
                || auth()->user()?->can('stock.view');
        @endphp
        @if ($allowed)
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs(...$item['active']) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                    {{ $item['label'] }}
                </a>
            </li>
        @endif
    @endforeach
</ul>
