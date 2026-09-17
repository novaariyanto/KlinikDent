<div class="dropdown">
    <button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">Action</button>
    <ul class="dropdown-menu dropdown-menu-end">
        @if ($queue->visit)
            <li><a class="dropdown-item" href="{{ route('registration.visits.show', $queue->visit) }}"><i class="bx bx-show-alt me-1"></i> Kunjungan</a></li>
            @if (App\Support\Clinical\CareTabs::canAccessCare(auth()->user()))
                <li><a class="dropdown-item" href="{{ route('care.show', $queue->visit) }}"><i class="bx bx-plus-medical me-1"></i> Buka Pelayanan</a></li>
            @endif
        @endif
        @can('call', $queue)
            <li>
                <form action="{{ route('queue.call', $queue) }}" method="POST">
                    @csrf
                    <button type="submit" class="dropdown-item"><i class="bx bx-bell me-1"></i> Panggil</button>
                </form>
            </li>
        @endcan
        @can('complete', $queue)
            <li>
                <form action="{{ route('queue.complete', $queue) }}" method="POST">
                    @csrf
                    <button type="submit" class="dropdown-item"><i class="bx bx-check me-1"></i> Selesai</button>
                </form>
            </li>
        @endcan
        @can('skip', $queue)
            <li>
                <form action="{{ route('queue.skip', $queue) }}" method="POST" onsubmit="return confirm('Lewati nomor ini?')">
                    @csrf
                    <button type="submit" class="dropdown-item text-danger"><i class="bx bx-skip-next me-1"></i> Lewati</button>
                </form>
            </li>
        @endcan
    </ul>
</div>
