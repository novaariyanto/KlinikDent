<div class="dropdown">
    <button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Action</button>
    <ul class="dropdown-menu dropdown-menu-end">
        @can('view', $doctor)
            <li><a class="dropdown-item" href="{{ route('doctors.show', $doctor) }}"><i class="bx bx-show-alt me-1"></i> View</a></li>
        @endcan
        @can('update', $doctor)
            <li><a class="dropdown-item" href="{{ route('doctors.edit', $doctor) }}"><i class="bx bx-edit me-1"></i> Edit</a></li>
        @endcan
        @can('delete', $doctor)
            <li><hr class="dropdown-divider"></li>
            <li>
                <form action="{{ route('doctors.destroy', $doctor) }}" method="POST" onsubmit="return confirm('Hapus profil tenaga medis ini? Akun login tidak ikut terhapus.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="dropdown-item text-danger"><i class="bx bx-trash me-1"></i> Delete</button>
                </form>
            </li>
        @endcan
    </ul>
</div>
