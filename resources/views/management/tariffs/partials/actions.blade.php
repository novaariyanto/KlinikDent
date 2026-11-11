<div class="d-inline-flex align-items-center gap-1">
    @can('view', $tariff)
        <a href="{{ route('management.tariffs.show', $tariff) }}" class="btn btn-soft-info btn-sm" title="Lihat" aria-label="Lihat">
            <i class="bx bx-show-alt"></i>
        </a>
    @endcan
    @can('update', $tariff)
        <a href="{{ route('management.tariffs.edit', $tariff) }}" class="btn btn-soft-primary btn-sm" title="Ubah" aria-label="Ubah">
            <i class="bx bx-edit"></i>
        </a>
    @endcan
    @can('delete', $tariff)
        <form action="{{ route('management.tariffs.destroy', $tariff) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus tarif ini?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-soft-danger btn-sm" title="Hapus" aria-label="Hapus">
                <i class="bx bx-trash"></i>
            </button>
        </form>
    @endcan
</div>
