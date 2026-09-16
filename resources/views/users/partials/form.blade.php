@php
    $user = $user ?? null;
    $roleOptions = $roles->mapWithKeys(fn ($role) => [$role->name => $role->name])->all();
    $statusOptions = collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all();
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
</div>
