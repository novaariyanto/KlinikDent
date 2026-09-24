@php
    $current = $current ?? request()->route()?->getName();
    $items = [
        ['route' => 'pharmacy.prescriptions.incoming', 'label' => 'Resep Masuk'],
        ['route' => 'pharmacy.prescriptions.processing', 'label' => 'Diproses'],
        ['route' => 'pharmacy.prescriptions.completed', 'label' => 'Selesai'],
        ['route' => 'pharmacy.prescriptions.history', 'label' => 'Riwayat'],
    ];
@endphp

<ul class="nav nav-tabs nav-tabs-custom mb-3">
    @foreach ($items as $item)
        <li class="nav-item">
            <a class="nav-link {{ $current === $item['route'] ? 'active' : '' }}" href="{{ route($item['route']) }}">
                {{ $item['label'] }}
            </a>
        </li>
    @endforeach
</ul>
