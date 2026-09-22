@php
    $items = [
        ['route' => 'whatsapp.dashboard', 'label' => 'Dashboard', 'permission' => 'whatsapp.view'],
        ['route' => 'whatsapp.balance', 'label' => 'Saldo', 'permission' => 'whatsapp.view'],
        ['route' => 'whatsapp.reminders', 'label' => 'Pengingat Jadwal', 'permission' => 'whatsapp.automation.view'],
        ['route' => 'whatsapp.promotions.index', 'label' => 'Promosi', 'permission' => 'whatsapp.promotions.view'],
        ['route' => 'whatsapp.templates.index', 'label' => 'Template Pesan', 'permission' => 'whatsapp.templates.view'],
        ['route' => 'whatsapp.logs.index', 'label' => 'Riwayat Pesan', 'permission' => 'whatsapp.logs.view'],
        ['route' => 'whatsapp.settings', 'label' => 'Pengaturan WhatsApp', 'permission' => 'whatsapp.settings.manage'],
    ];
@endphp

<ul class="nav nav-tabs nav-tabs-custom mb-3">
    @foreach ($items as $item)
        @if (auth()->user()?->can($item['permission']) || ($item['route'] === 'whatsapp.settings' && auth()->user()?->can('whatsapp.view')))
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs($item['route']) || request()->routeIs($item['route'].'*') || request()->routeIs(str_replace('.index', '.*', $item['route'])) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                    {{ $item['label'] }}
                </a>
            </li>
        @endif
    @endforeach
</ul>
