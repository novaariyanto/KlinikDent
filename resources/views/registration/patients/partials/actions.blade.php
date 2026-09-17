<div class="dropdown">
    <button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">Action</button>
    <ul class="dropdown-menu dropdown-menu-end">
        @can('view', $patient)
            <li><a class="dropdown-item" href="{{ route('registration.patients.show', $patient) }}"><i class="bx bx-show-alt me-1"></i> View</a></li>
        @endcan
        @can('update', $patient)
            <li><a class="dropdown-item" href="{{ route('registration.patients.edit', $patient) }}"><i class="bx bx-edit me-1"></i> Edit</a></li>
        @endcan
        @can('create', App\Models\Visit::class)
            <li><a class="dropdown-item" href="{{ route('registration.new', ['patient_id' => $patient->id]) }}"><i class="bx bx-plus me-1"></i> Daftarkan</a></li>
        @endcan
        @can('delete', $patient)
            <li><hr class="dropdown-divider"></li>
            <li>
                <form action="{{ route('registration.patients.destroy', $patient) }}" method="POST" onsubmit="return confirm('Delete this patient?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="dropdown-item text-danger"><i class="bx bx-trash me-1"></i> Delete</button>
                </form>
            </li>
        @endcan
    </ul>
</div>
