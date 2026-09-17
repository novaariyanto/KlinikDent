@php
    $user = $user ?? null;
    $roleOptions = $roles->mapWithKeys(fn ($role) => [$role->name => $role->name])->all();
    $statusOptions = collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all();
    $tenantOptions = collect($tenants ?? [])->mapWithKeys(fn ($tenant) => [$tenant->id => $tenant->name])->all();
    $branchOptions = collect($branches ?? [])->mapWithKeys(function ($branch) {
        $label = $branch->tenant?->name
            ? $branch->tenant->name.' — '.$branch->name
            : $branch->name;

        return [$branch->id => $label];
    })->all();
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
    <div class="col-md-6">
        <x-select
            name="role"
            label="Role"
            :options="$roleOptions"
            :selected="old('role', isset($user) ? $user->roles->first()?->name : null)"
            required
        />
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
    <div class="col-md-6">
        <x-select
            name="branch_id"
            label="Cabang"
            :options="$branchOptions"
            :selected="old('branch_id', $user?->branch_id)"
            placeholder="Tanpa cabang"
        />
    </div>
</div>
