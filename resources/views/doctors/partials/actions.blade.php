<div class="d-flex flex-wrap gap-2">
    @can('view', $doctor)
        @php
            $branchNames = $doctor->user?->branches->pluck('name') ?? collect();
            if ($branchNames->isEmpty() && $doctor->user?->branch) {
                $branchNames = collect([$doctor->user->branch->name]);
            }
            $accountUrl = ($doctor->user && auth()->user()?->can('users.view'))
                ? route('users.show', $doctor->user)
                : '';
        @endphp
        <button
            type="button"
            class="btn btn-sm btn-soft-secondary"
            data-bs-toggle="modal"
            data-bs-target="#doctor-view-modal"
            data-doctor="{{ json_encode([
                'name' => $doctor->displayName(),
                'email' => $doctor->user?->email ?: '-',
                'role' => $doctor->roleLabel(),
                'specialization' => $doctor->specialization ?: '-',
                'sip' => $doctor->sip ?: '-',
                'str' => $doctor->str ?: '-',
                'nik' => $doctor->nik ?: '-',
                'phone' => $doctor->phone ?: '-',
                'branch' => $branchNames->implode(', ') ?: '-',
                'is_active' => (bool) $doctor->is_active,
                'notes' => $doctor->notes ?: '',
                'signature' => $doctor->signature_path ? asset('storage/'.$doctor->signature_path) : '',
                'account_url' => $accountUrl,
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) }}"
        ><i class="bx bx-show-alt me-1"></i> Detail</button>
    @endcan
    @can('update', $doctor)
        <a href="{{ route('doctors.edit', $doctor) }}" class="btn btn-sm btn-soft-primary">
            <i class="bx bx-edit me-1"></i> Edit
        </a>
    @endcan
    @can('delete', $doctor)
        <form action="{{ route('doctors.destroy', $doctor) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus profil tenaga medis ini? Akun login tidak ikut terhapus.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-soft-danger"><i class="bx bx-trash me-1"></i> Hapus</button>
        </form>
    @endcan
</div>
