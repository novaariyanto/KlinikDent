<div class="dropdown">
    <button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Action</button>
    <ul class="dropdown-menu dropdown-menu-end">
        @can('view', $medicine)
            <li><a class="dropdown-item" href="{{ route('management.medicines.show', $medicine) }}"><i class="bx bx-show-alt me-1"></i> View</a></li>
        @endcan
        @can('update', $medicine)
            <li><a class="dropdown-item" href="{{ route('management.medicines.edit', $medicine) }}"><i class="bx bx-edit me-1"></i> Edit</a></li>
        @endcan
        @can('delete', $medicine)
            <li><hr class="dropdown-divider"></li>
            <li>
                <form action="{{ route('management.medicines.destroy', $medicine) }}" method="POST" onsubmit="return confirm('Delete this medicine?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="dropdown-item text-danger"><i class="bx bx-trash me-1"></i> Delete</button>
                </form>
            </li>
        @endcan
    </ul>
</div>
