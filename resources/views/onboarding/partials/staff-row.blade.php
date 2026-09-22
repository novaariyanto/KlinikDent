@php
    $roles = $selectedRoles ?: $recommendedRoles;
    $rowRoles = collect($row['roles'] ?? []);
@endphp

<div class="card mb-3 staff-row">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">Staf</h5>
            <button type="button" class="btn btn-sm btn-outline-danger js-remove-row">Hapus</button>
        </div>
        <div class="row">
            <div class="col-md-4">
                <x-input :name="'staff['.$index.'][name]'" label="Nama" :value="$row['name'] ?? ''" />
            </div>
            <div class="col-md-4">
                <x-input :name="'staff['.$index.'][email]'" type="email" label="Email" :value="$row['email'] ?? ''" />
            </div>
            <div class="col-md-4">
                <x-input :name="'staff['.$index.'][password]'" type="password" label="Password" />
            </div>
            <div class="col-12">
                <label class="form-label">Role</label>
                <div class="row">
                    @foreach ($roles as $role)
                        @continue($role === \App\Enums\RoleName::Owner->value)
                        @php $enum = \App\Enums\RoleName::tryFrom($role); @endphp
                        <div class="col-md-4 col-lg-3">
                            <div class="form-check mb-2">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="staff[{{ $index }}][roles][]"
                                    id="staff-{{ $index }}-{{ $role }}"
                                    value="{{ $role }}"
                                    @checked($rowRoles->contains($role))
                                >
                                <label class="form-check-label" for="staff-{{ $index }}-{{ $role }}">{{ $enum?->label() ?? $role }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
