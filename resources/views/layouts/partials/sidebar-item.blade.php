@php
    use App\Enums\MenuType;

    $isActive = $menu->isCurrent();
    $hasChildren = $menu->children->isNotEmpty();
@endphp

@if ($menu->type === MenuType::Heading)
    <li class="menu-title">{{ $menu->title }}</li>
    @foreach ($menu->children as $child)
        @include('layouts.partials.sidebar-item', ['menu' => $child])
    @endforeach
@elseif ($hasChildren)
    <li class="{{ $isActive ? 'mm-active' : '' }}">
        <a href="javascript:void(0);" class="has-arrow waves-effect {{ $isActive ? 'active' : '' }}">
            @if ($menu->icon)
                <i class="{{ $menu->icon }}"></i>
            @endif
            <span>{{ $menu->title }}</span>
        </a>
        <ul class="sub-menu" aria-expanded="{{ $isActive ? 'true' : 'false' }}">
            @foreach ($menu->children as $child)
                @include('layouts.partials.sidebar-item', ['menu' => $child])
            @endforeach
        </ul>
    </li>
@else
    <li class="{{ $isActive ? 'mm-active' : '' }}">
        <a href="{{ $menu->href() }}" class="waves-effect {{ $isActive ? 'active' : '' }}">
            @if ($menu->icon)
                <i class="{{ $menu->icon }}"></i>
            @endif
            <span>{{ $menu->title }}</span>
        </a>
    </li>
@endif
