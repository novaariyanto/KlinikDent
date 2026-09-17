<div class="dropdown">
    <button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Action</button>
    <ul class="dropdown-menu dropdown-menu-end">
        @can('view', $room)
            <li><a class="dropdown-item" href="{{ route('management.rooms.show', $room) }}"><i class="bx bx-show-alt me-1"></i> View</a></li>
        @endcan
        @can('update', $room)
            <li><a class="dropdown-item" href="{{ route('management.rooms.edit', $room) }}"><i class="bx bx-edit me-1"></i> Edit</a></li>
        @endcan
        @can('delete', $room)
            <li><hr class="dropdown-divider"></li>
            <li>
                <form action="{{ route('management.rooms.destroy', $room) }}" method="POST" onsubmit="return confirm('Delete this room?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="dropdown-item text-danger"><i class="bx bx-trash me-1"></i> Delete</button>
                </form>
            </li>
        @endcan
    </ul>
</div>
