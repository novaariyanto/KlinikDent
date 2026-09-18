@php
    $user = $user ?? null;
    $selectedRoles = collect(old('roles', isset($user) ? $user->roles->pluck('name')->all() : []))->map(fn ($name) => (string) $name);
    $statusOptions = collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all();
    $tenantOptions = collect($tenants ?? [])->mapWithKeys(fn ($tenant) => [$tenant->id => $tenant->name])->all();
@endphp

<div class="row">
    <div class="col-md-6">
        <x-input name="name" label="Name" :value="$user?->name" required />
    </div>
    <div class="col-md-6">
        <x-input name="email" type="email" label="Email" :value="$user?->email" required />
    </div>
    <div class="col-md-6">
        <x-input name="password" type="password" label="Password" :required="! $user" autocomplete="new-password" />
    </div>
    <div class="col-md-6">
        <x-input name="password_confirmation" type="password" label="Confirm Password" :required="! $user" autocomplete="new-password" />
    </div>
    <div class="col-12">
        <label class="form-label">Role <span class="text-danger">*</span></label>
        <p class="text-muted small mb-2">Boleh lebih dari satu. Klinik kecil biasanya cukup Owner, Dokter, Pendaftaran, Kasir, Farmasi.</p>
        <div class="row">
            @foreach ($roles as $role)
                @php
                    $roleEnum = \App\Enums\RoleName::tryFrom($role->name);
                    $label = $roleEnum?->label() ?? $role->name;
                @endphp
                <div class="col-md-4 col-lg-3">
                    <div class="form-check mb-2">
                        <input
                            class="form-check-input @error('roles') is-invalid @enderror"
                            type="checkbox"
                            name="roles[]"
                            id="role-{{ $role->name }}"
                            value="{{ $role->name }}"
                            @checked($selectedRoles->contains($role->name))
                        >
                        <label class="form-check-label" for="role-{{ $role->name }}">{{ $label }}</label>
                    </div>
                </div>
            @endforeach
        </div>
        @error('roles')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
        @error('roles.*')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
    <div class="col-md-6">
        <x-select
            name="status"
            label="Status"
            :options="$statusOptions"
            :selected="old('status', isset($user) ? $user->status->value : 'active')"
            required
        />
    </div>
    @if (auth()->user()?->isPlatformAdmin())
        <div class="col-md-6">
            <x-select
                name="tenant_id"
                label="Klinik"
                :options="$tenantOptions"
                :selected="old('tenant_id', $user?->tenant_id)"
                placeholder="Platform (tanpa tenant)"
            />
        </div>
    @endif
    <div class="col-12">
        <label class="form-label">Cabang</label>
        <p class="text-muted small mb-2">Boleh lebih dari satu. Cabang pertama menjadi cabang utama.</p>
        <div class="row">
            @php
                $selectedBranches = collect(old('branch_ids', isset($user) ? $user->assignedBranchIds() : []))->map(fn ($id) => (string) $id);
            @endphp
            @forelse ($branches as $branch)
                @php
                    $label = $branch->tenant?->name
                        ? $branch->tenant->name.' — '.$branch->name
                        : $branch->name;
                @endphp
                <div class="col-md-4 col-lg-3">
                    <div class="form-check mb-2">
                        <input
                            class="form-check-input @error('branch_ids') is-invalid @enderror"
                            type="checkbox"
                            name="branch_ids[]"
                            id="branch-{{ $branch->id }}"
                            value="{{ $branch->id }}"
                            @checked($selectedBranches->contains((string) $branch->id))
                        >
                        <label class="form-check-label" for="branch-{{ $branch->id }}">{{ $label }}</label>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <p class="text-muted mb-0">Belum ada cabang.</p>
                </div>
            @endforelse
        </div>
        @error('branch_ids')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
</div>
