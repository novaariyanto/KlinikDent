<div class="dropdown">
    <button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        Action
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        @can('menus.edit')
            <li>
                <a class="dropdown-item" href="{{ route('menus.edit', $menu) }}">
                    <i class="bx bx-edit me-1"></i> Edit
                </a>
            </li>
            <li>
                <form action="{{ route('menus.toggle-status', $menu) }}" method="POST">
                    @csrf
                    <button type="submit" class="dropdown-item">
                        <i class="bx bx-refresh me-1"></i>
                        {{ $menu->status === \App\Enums\UserStatus::Active ? 'Deactivate' : 'Activate' }}
                    </button>
                </form>
            </li>
        @endcan
        @can('menus.delete')
            <li><hr class="dropdown-divider"></li>
            <li>
                <form action="{{ route('menus.destroy', $menu) }}" method="POST"
                    onsubmit="return confirm('Delete this menu?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="dropdown-item text-danger">
                        <i class="bx bx-trash me-1"></i> Delete
                    </button>
                </form>
            </li>
        @endcan
    </ul>
</div>
