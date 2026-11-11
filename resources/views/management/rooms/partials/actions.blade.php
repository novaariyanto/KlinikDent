<div class="d-inline-flex align-items-center gap-1">
    @can('view', $room)
        <a href="{{ route('management.rooms.show', $room) }}" class="btn btn-soft-info btn-sm" title="Lihat" aria-label="Lihat">
            <i class="bx bx-show-alt"></i>
        </a>
    @endcan
    @can('update', $room)
        <a href="{{ route('management.rooms.edit', $room) }}" class="btn btn-soft-primary btn-sm" title="Ubah" aria-label="Ubah">
            <i class="bx bx-edit"></i>
        </a>
    @endcan
    @if ($room->type === \App\Enums\RoomType::Poli && ! filled($room->satusehatLocation?->satusehat_id))
        @can('update', $room)
            <form action="{{ route('management.rooms.satusehat', $room) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-soft-secondary btn-sm" title="Ambil IHS" aria-label="Ambil IHS">
                    <i class="bx bx-cloud-download"></i>
                </button>
            </form>
        @endcan
    @endif
    @can('delete', $room)
        <form action="{{ route('management.rooms.destroy', $room) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus ruangan ini?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-soft-danger btn-sm" title="Hapus" aria-label="Hapus">
                <i class="bx bx-trash"></i>
            </button>
        </form>
    @endcan
</div>
