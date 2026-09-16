<div class="vertical-menu">
    <div data-simplebar class="h-100">
        <div id="sidebar-menu">
            <ul class="metismenu list-unstyled" id="side-menu">
                @can('dashboard.view')
                    <li class="{{ request()->routeIs('dashboard') ? 'mm-active' : '' }}">
                        <a href="{{ route('dashboard') }}" class="waves-effect {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <i class="bx bx-home-circle"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                @endcan

                @canany(['users.view', 'roles.view'])
                    <li class="menu-title">Management</li>
                @endcanany

                @can('users.view')
                    <li class="{{ request()->routeIs('users.*') ? 'mm-active' : '' }}">
                        <a href="{{ route('users.index') }}" class="waves-effect {{ request()->routeIs('users.*') ? 'active' : '' }}">
                            <i class="bx bx-user"></i>
                            <span>Users</span>
                        </a>
                    </li>
                @endcan

                @can('roles.view')
                    <li class="{{ request()->routeIs('roles.*') ? 'mm-active' : '' }}">
                        <a href="{{ route('roles.index') }}" class="waves-effect {{ request()->routeIs('roles.*') ? 'active' : '' }}">
                            <i class="bx bx-shield-quarter"></i>
                            <span>Roles & Permissions</span>
                        </a>
                    </li>
                @endcan

                <li class="menu-title">System</li>
                <li class="{{ request()->routeIs('settings.*') ? 'mm-active' : '' }}">
                    <a href="{{ route('settings.index') }}" class="waves-effect {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                        <i class="bx bx-cog"></i>
                        <span>Settings</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>
</div>
