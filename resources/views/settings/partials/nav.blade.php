@if (auth()->user()?->hasRole('owner'))
    <ul class="nav nav-tabs nav-tabs-custom mb-3">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('settings.clinic') ? 'active' : '' }}" href="{{ route('settings.clinic') }}">Pengaturan Klinik</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('archive.*') ? 'active' : '' }}" href="{{ route('archive.index') }}">Arsip Praktik</a>
        </li>
    </ul>
@endif
