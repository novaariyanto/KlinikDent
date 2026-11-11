<div class="d-inline-flex align-items-center gap-1">
    @can('view', $procedure)
        <a href="{{ route('management.procedures.show', $procedure) }}" class="btn btn-soft-info btn-sm" title="Lihat" aria-label="Lihat">
            <i class="bx bx-show-alt"></i>
        </a>
    @endcan
    @can('update', $procedure)
        <a href="{{ route('management.procedures.edit', $procedure) }}" class="btn btn-soft-primary btn-sm" title="Ubah" aria-label="Ubah">
            <i class="bx bx-edit"></i>
        </a>
    @endcan
    @can('delete', $procedure)
        <form action="{{ route('management.procedures.destroy', $procedure) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus tindakan ini?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-soft-danger btn-sm" title="Hapus" aria-label="Hapus">
                <i class="bx bx-trash"></i>
            </button>
        </form>
    @endcan
</div>
