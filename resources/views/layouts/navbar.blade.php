<header id="page-topbar">
    <div class="navbar-header">
        <div class="d-flex">
            <div class="navbar-brand-box">
                <a href="{{ route('dashboard') }}" class="logo logo-dark">
                    <span class="logo-sm">
                        <img src="{{ app_logo('dark') }}" alt="{{ $appName }}" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="{{ app_logo('dark') }}" alt="{{ $appName }}" height="22">
                    </span>
                </a>
                <a href="{{ route('dashboard') }}" class="logo logo-light">
                    <span class="logo-sm">
                        <img src="{{ app_logo('light') }}" alt="{{ $appName }}" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="{{ app_logo('light') }}" alt="{{ $appName }}" height="22">
                    </span>
                </a>
            </div>

            <button type="button" class="btn btn-sm px-3 font-size-16 header-item waves-effect" id="vertical-menu-btn">
                <i class="fa fa-fw fa-bars"></i>
            </button>
        </div>

        <div class="d-flex">
            <div class="dropdown d-none d-lg-inline-block ms-1">
                <button type="button" class="btn header-item noti-icon waves-effect" data-toggle="fullscreen">
                    <i class="bx bx-fullscreen"></i>
                </button>
            </div>

            <div class="dropdown d-inline-block">
                <button type="button" class="btn header-item waves-effect" id="page-header-user-dropdown"
                    data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <img class="rounded-circle header-profile-user" src="{{ theme('images/users/avatar-1.jpg') }}"
                        alt="{{ auth()->user()->name }}">
                    <span class="d-none d-xl-inline-block ms-1" key="t-user">{{ auth()->user()->name }}</span>
                    @if (is_impersonating())
                        <span class="badge bg-warning text-dark d-none d-xl-inline-block ms-1">Impersonating</span>
                    @endif
                    <i class="mdi mdi-chevron-down d-none d-xl-inline-block"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    @can('setting.view')
                        <a class="dropdown-item" href="{{ route('settings.clinic') }}">
                            <i class="bx bx-cog font-size-16 align-middle me-1"></i>
                            <span>Pengaturan Klinik</span>
                        </a>
                    @endcan
                    @if (auth()->user()?->isPlatformAdmin())
                        @can('settings.update')
                            <a class="dropdown-item" href="{{ route('settings.index') }}">
                                <i class="bx bx-slider-alt font-size-16 align-middle me-1"></i>
                                <span>Pengaturan Platform</span>
                            </a>
                        @endcan
                    @endif
                    @if (is_impersonating())
                        <form method="POST" action="{{ route('impersonate.leave') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-warning">
                                <i class="bx bx-undo font-size-16 align-middle me-1 text-warning"></i>
                                <span>Leave impersonation</span>
                            </button>
                        </form>
                        <div class="dropdown-divider"></div>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bx bx-power-off font-size-16 align-middle me-1 text-danger"></i>
                            <span>Logout</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
