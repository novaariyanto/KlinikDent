<div class="vertical-menu">
    @if (request()->routeIs('care.show'))
        @include('layouts.partials.care-sidebar')
    @else
        <div data-simplebar class="h-100">
            <div id="sidebar-menu">
                <ul class="metismenu list-unstyled" id="side-menu">
                    @foreach ($sidebarMenus ?? [] as $sidebarMenu)
                        @include('layouts.partials.sidebar-item', ['menu' => $sidebarMenu])
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
</div>
