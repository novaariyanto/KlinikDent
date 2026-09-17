<div class="dropdown">
    <button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        Action
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        @can('view', $service)
            <li>
                <a class="dropdown-item" href="{{ route('management.services.show', $service) }}">
                    <i class="bx bx-show-alt me-1"></i> View
                </a>
            </li>
        @endcan
        @can('update', $service)
            <li>
                <a class="dropdown-item" href="{{ route('management.services.edit', $service) }}">
                    <i class="bx bx-edit me-1"></i> Edit
                </a>
            </li>
        @endcan
        @can('delete', $service)
            <li><hr class="dropdown-divider"></li>
            <li>
                <form action="{{ route('management.services.destroy', $service) }}" method="POST"
                    onsubmit="return confirm('Delete this service?')">
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
