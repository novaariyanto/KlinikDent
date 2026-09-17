<div class="dropdown">
    <button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        Action
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        @can('view', $branch)
            <li>
                <a class="dropdown-item" href="{{ route('branches.show', $branch) }}">
                    <i class="bx bx-show-alt me-1"></i> View
                </a>
            </li>
        @endcan
        @can('update', $branch)
            <li>
                <a class="dropdown-item" href="{{ route('branches.edit', $branch) }}">
                    <i class="bx bx-edit me-1"></i> Edit
                </a>
            </li>
        @endcan
        @can('delete', $branch)
            <li><hr class="dropdown-divider"></li>
            <li>
                <form action="{{ route('branches.destroy', $branch) }}" method="POST"
                    onsubmit="return confirm('Delete this branch?')">
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
