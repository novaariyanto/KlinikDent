<div class="vertical-menu">
    <div data-simplebar class="h-100">
        <div id="sidebar-menu">
            <ul class="metismenu list-unstyled" id="side-menu">
                @foreach ($sidebarMenus ?? [] as $sidebarMenu)
                    @include('layouts.partials.sidebar-item', ['menu' => $sidebarMenu])
                @endforeach
            </ul>
        </div>
    </div>
</div>
