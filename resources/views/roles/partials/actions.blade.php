<div class="dropdown">
    <button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        Action
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        @can('roles.view')
            <li>
                <a class="dropdown-item" href="{{ route('roles.show', $role) }}">
                    <i class="bx bx-show-alt me-1"></i> View
                </a>
            </li>
        @endcan
        @can('roles.edit')
            <li>
                <a class="dropdown-item" href="{{ route('roles.edit', $role) }}">
                    <i class="bx bx-edit me-1"></i> Edit
                </a>
            </li>
        @endcan
        @can('roles.delete')
            @if (! \App\Enums\RoleName::isSystem($role->name))
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form action="{{ route('roles.destroy', $role) }}" method="POST"
                        onsubmit="return confirm('Delete this role?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bx bx-trash me-1"></i> Delete
                        </button>
                    </form>
                </li>
            @endif
        @endcan
    </ul>
</div>
