<div class="dropdown">
    <button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        Action
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        @can('logs.view')
            <li>
                <a class="dropdown-item" href="{{ route('logs.show', $log) }}">
                    <i class="bx bx-show-alt me-1"></i> View
                </a>
            </li>
        @endcan
        @can('logs.delete')
            <li><hr class="dropdown-divider"></li>
            <li>
                <form action="{{ route('logs.destroy', $log) }}" method="POST"
                    onsubmit="return confirm('Delete this log entry?')">
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
