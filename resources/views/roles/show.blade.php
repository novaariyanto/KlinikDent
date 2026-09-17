@extends('layouts.app')

@section('title', 'Role '.$role->name)

@section('content')
    <x-page-header title="{{ $role->name }}" :breadcrumb="['Roles' => route('roles.index'), $role->name => null]" />

    <x-alert />

    <x-card title="Permissions">
        <p class="text-muted">Tampilan read-only. Ubah permission hanya jika Anda memiliki hak edit role.</p>

        <div class="row">
            @foreach ($permissionGroups as $group => $permissions)
                <div class="col-md-6 col-xl-4 mb-3">
                    <div class="border rounded p-3 h-100">
                        <h6 class="text-uppercase text-muted mb-3">{{ ucfirst($group) }}</h6>
                        <ul class="list-unstyled mb-0">
                            @foreach ($permissions as $permission)
                                <li class="mb-1">
                                    @if ($role->permissions->contains('name', $permission->name))
                                        <i class="bx bx-check-circle text-success"></i>
                                    @else
                                        <i class="bx bx-circle text-muted"></i>
                                    @endif
                                    {{ $permission->name }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="d-flex gap-2 mt-2">
            @can('roles.edit')
                <x-button href="{{ route('roles.edit', $role) }}" icon="bx bx-edit">Edit</x-button>
            @endcan
            <x-button href="{{ route('roles.index') }}" variant="secondary">Back</x-button>
        </div>
    </x-card>
@endsection
