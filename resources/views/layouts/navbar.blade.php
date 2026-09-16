<header id="page-topbar">
    <div class="navbar-header">
        <div class="d-flex">
            <div class="navbar-brand-box">
                <a href="{{ route('dashboard') }}" class="logo logo-dark">
                    <span class="logo-sm">
                        <img src="{{ theme('images/logo.svg') }}" alt="{{ $appName }}" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="{{ theme('images/logo-dark.png') }}" alt="{{ $appName }}" height="17">
                    </span>
                </a>
                <a href="{{ route('dashboard') }}" class="logo logo-light">
                    <span class="logo-sm">
                        <img src="{{ theme('images/logo-light.svg') }}" alt="{{ $appName }}" height="22">
                    </span>
                    <span class="logo-lg">
                        <img src="{{ theme('images/logo-light.png') }}" alt="{{ $appName }}" height="19">
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
                    <i class="mdi mdi-chevron-down d-none d-xl-inline-block"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="{{ route('settings.index') }}">
                        <i class="bx bx-cog font-size-16 align-middle me-1"></i>
                        <span>Settings</span>
                    </a>
                    <div class="dropdown-divider"></div>
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
