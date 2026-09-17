@php
    $menu = $menu ?? null;
    $parentOptions = $parents
        ->mapWithKeys(fn ($parent) => [$parent->id => $parent->title])
        ->all();
    $typeOptions = collect($types)->mapWithKeys(fn ($type) => [$type->value => $type->label()])->all();
    $statusOptions = collect($statuses)->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all();
    $permissionOptions = collect($permissions)->all();
@endphp

<div class="row">
    <div class="col-md-6">
        <x-input name="title" label="Title" :value="$menu?->title" required />
    </div>
    <div class="col-md-6">
        <x-select
            name="type"
            label="Type"
            :options="$typeOptions"
            :selected="old('type', $menu?->type->value ?? 'item')"
            required
        />
    </div>
    <div class="col-md-6">
        <x-input name="icon" label="Icon" :value="$menu?->icon" placeholder="bx bx-home-circle" />
        <small class="text-muted d-block mb-3 mt-n2">Boxicons class, contoh: <code>bx bx-user</code></small>
    </div>
    <div class="col-md-6">
        <x-select
            name="parent_id"
            label="Parent"
            :options="$parentOptions"
            :selected="old('parent_id', $menu?->parent_id)"
            placeholder="Root menu"
        />
    </div>
    <div class="col-md-6">
        <x-input name="route_name" label="Route Name" :value="$menu?->route_name" placeholder="users.index" />
    </div>
    <div class="col-md-6">
        <x-input name="url" label="URL" :value="$menu?->url" placeholder="/custom-path" />
    </div>
    <div class="col-md-6">
        <x-select
            name="permission"
            label="Permission"
            :options="$permissionOptions"
            :selected="old('permission', $menu?->permission)"
            placeholder="Visible to all authenticated users"
        />
    </div>
    <div class="col-md-3">
        <x-input name="sort_order" type="number" label="Order" :value="old('sort_order', $menu?->sort_order ?? 0)" required />
    </div>
    <div class="col-md-3">
        <x-select
            name="status"
            label="Status"
            :options="$statusOptions"
            :selected="old('status', $menu?->status->value ?? 'active')"
            required
        />
    </div>
</div>
