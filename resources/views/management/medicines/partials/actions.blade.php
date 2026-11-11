<div class="d-inline-flex align-items-center gap-1">
    @can('view', $medicine)
        <a href="{{ route('management.medicines.show', $medicine) }}" class="btn btn-soft-info btn-sm" title="Lihat" aria-label="Lihat">
            <i class="bx bx-show-alt"></i>
        </a>
    @endcan
    @can('update', $medicine)
        <a href="{{ route('management.medicines.edit', $medicine) }}" class="btn btn-soft-primary btn-sm" title="Ubah" aria-label="Ubah">
            <i class="bx bx-edit"></i>
        </a>
    @endcan
    @can('delete', $medicine)
        <form action="{{ route('management.medicines.destroy', $medicine) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus obat ini?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-soft-danger btn-sm" title="Hapus" aria-label="Hapus">
                <i class="bx bx-trash"></i>
            </button>
        </form>
    @endcan
</div>
