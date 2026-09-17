<div class="dropdown">
    <button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">Action</button>
    <ul class="dropdown-menu dropdown-menu-end">
        @can('view', $visit)
            <li><a class="dropdown-item" href="{{ route('registration.visits.show', $visit) }}"><i class="bx bx-show-alt me-1"></i> View</a></li>
        @endcan
        @if (App\Support\Clinical\CareTabs::canAccessCare(auth()->user()))
            <li><a class="dropdown-item" href="{{ route('care.show', $visit) }}"><i class="bx bx-plus-medical me-1"></i> Buka Pelayanan</a></li>
        @endif
        @can('cancel', $visit)
            <li>
                <form action="{{ route('registration.visits.cancel', $visit) }}" method="POST" onsubmit="return confirm('Batalkan kunjungan ini?')">
                    @csrf
                    <button type="submit" class="dropdown-item text-danger"><i class="bx bx-x me-1"></i> Batal</button>
                </form>
            </li>
        @endcan
    </ul>
</div>
