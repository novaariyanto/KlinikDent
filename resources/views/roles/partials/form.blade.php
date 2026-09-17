@php
    $selected = collect(old('permissions', isset($role) ? $role->permissions->pluck('name')->all() : []));
@endphp

<div class="mb-3">
    <x-input
        name="name"
        label="Role"
        :value="$role->name ?? null"
        :required="true"
        :disabled="isset($role) && \App\Enums\RoleName::isSystem($role->name)"
        />
    @if (isset($role) && \App\Enums\RoleName::isSystem($role->name))
        <input type="hidden" name="name" value="{{ $role->name }}">
    @endif
</div>

<div class="mb-3">
    <label class="form-label">Permissions</label>
    <div class="row">
        @foreach ($permissionGroups as $group => $permissions)
            <div class="col-md-6 col-xl-4 mb-3">
                <div class="border rounded p-3 h-100">
                    <h6 class="text-uppercase text-muted mb-3">{{ ucfirst($group) }}</h6>
                    @foreach ($permissions as $permission)
                        <div class="form-check mb-2">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="permissions[]"
                                id="permission-{{ $permission->id }}"
                                value="{{ $permission->name }}"
                                @checked($selected->contains($permission->name))
                            >
                            <label class="form-check-label" for="permission-{{ $permission->id }}">
                                {{ $permission->name }}
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
    @error('permissions')
        <div class="text-danger small">{{ $message }}</div>
    @enderror
</div>
