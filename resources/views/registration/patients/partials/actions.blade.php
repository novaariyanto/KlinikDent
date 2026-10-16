<div class="dropdown">
    <button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">Action</button>
    <ul class="dropdown-menu dropdown-menu-end">
        @can('view', $patient)
            <li><a class="dropdown-item" href="{{ route('registration.patients.show', $patient) }}"><i class="bx bx-show-alt me-1"></i> View</a></li>
        @endcan
        @can('update', $patient)
            <li><a class="dropdown-item" href="{{ route('registration.patients.edit', $patient) }}"><i class="bx bx-edit me-1"></i> Edit</a></li>
            @if (filled($patient->nik) && ! filled($patient->satusehat_id))
                @if ($patient->hasSatuSehatAddress())
                    <li>
                        <form action="{{ route('registration.patients.satusehat', $patient) }}" method="POST">
                            @csrf
                            <button type="submit" class="dropdown-item"><i class="bx bx-cloud-download me-1"></i> Ambil IHS</button>
                        </form>
                    </li>
                @else
                    <li>
                        <a class="dropdown-item" href="{{ route('registration.patients.edit', ['patient' => $patient, 'ihs' => 1]) }}">
                            <i class="bx bx-cloud-download me-1"></i> Lengkapi & Ambil IHS
                        </a>
                    </li>
                @endif
            @endif
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
