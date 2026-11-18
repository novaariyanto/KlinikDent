<div class="d-inline-flex align-items-center gap-1">
    <a href="{{ route('cms.posts.edit', $post) }}" class="btn btn-soft-primary btn-sm" title="Ubah" aria-label="Ubah">
        <i class="bx bx-edit"></i>
    </a>
    <a href="{{ route('cms.posts.preview', $post) }}" class="btn btn-soft-secondary btn-sm" title="Preview" aria-label="Preview" target="_blank">
        <i class="bx bx-show-alt"></i>
    </a>
    @can('cms.manage')
        <form action="{{ route('cms.posts.duplicate', $post) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-soft-info btn-sm" title="Duplikat" aria-label="Duplikat">
                <i class="bx bx-copy"></i>
            </button>
        </form>
    @endcan
</div>
