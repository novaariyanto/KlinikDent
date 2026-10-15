<div class="dropdown">
    <button class="btn btn-soft-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Action</button>
    <ul class="dropdown-menu dropdown-menu-end">
        @can('view', $doctor)
            <li><a class="dropdown-item" href="{{ route('doctors.show', $doctor) }}"><i class="bx bx-show-alt me-1"></i> View</a></li>
        @endcan
        @can('update', $doctor)
            <li>
                <button
                    type="button"
                    class="dropdown-item"
                    data-bs-toggle="modal"
                    data-bs-target="#doctor-edit-modal"
                    data-doctor="{{ json_encode([
                        'id' => $doctor->id,
                        'name' => $doctor->user?->name ?? '',
                        'specialization' => $doctor->specialization ?? '',
                        'sip' => $doctor->sip ?? '',
                        'str' => $doctor->str ?? '',
                        'nik' => $doctor->nik ?? '',
                        'phone' => $doctor->phone ?? '',
                        'notes' => $doctor->notes ?? '',
                        'is_active' => (bool) $doctor->is_active,
                        'signature' => $doctor->signature_path ? asset('storage/'.$doctor->signature_path) : '',
                        'update_url' => route('doctors.update', $doctor),
                    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }}"
                ><i class="bx bx-edit me-1"></i> Edit</button>
            </li>
        @endcan
        @can('delete', $doctor)
            <li><hr class="dropdown-divider"></li>
            <li>
                <form action="{{ route('doctors.destroy', $doctor) }}" method="POST" onsubmit="return confirm('Hapus profil tenaga medis ini? Akun login tidak ikut terhapus.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="dropdown-item text-danger"><i class="bx bx-trash me-1"></i> Delete</button>
                </form>
            </li>
        @endcan
    </ul>
</div>
