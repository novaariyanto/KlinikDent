<div class="d-inline-flex align-items-center gap-1">
    @can('view', $service)
        <a href="{{ route('management.services.show', $service) }}" class="btn btn-soft-info btn-sm" title="Lihat" aria-label="Lihat">
            <i class="bx bx-show-alt"></i>
        </a>
    @endcan
    @can('update', $service)
        <a href="{{ route('management.services.edit', $service) }}" class="btn btn-soft-primary btn-sm" title="Ubah" aria-label="Ubah">
            <i class="bx bx-edit"></i>
        </a>
    @endcan
    @can('delete', $service)
        <form action="{{ route('management.services.destroy', $service) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus layanan ini?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-soft-danger btn-sm" title="Hapus" aria-label="Hapus">
                <i class="bx bx-trash"></i>
            </button>
        </form>
    @endcan
</div>
