@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <x-page-header title="Dashboard" :breadcrumb="['Dashboards' => route('dashboard'), 'Dashboard' => null]" />

    <div class="row">
        <div class="col-md-6 col-xl-3">
            <div class="card mini-stats-wid">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted fw-medium">Total Users</p>
                            <h4 class="mb-0">{{ number_format($stats['total_users']) }}</h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="mini-stat-icon avatar-sm rounded-circle bg-primary">
                                <span class="avatar-title">
                                    <i class="bx bx-user font-size-24"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card mini-stats-wid">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted fw-medium">Active Users</p>
                            <h4 class="mb-0">{{ number_format($stats['active_users']) }}</h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="mini-stat-icon avatar-sm rounded-circle bg-success">
                                <span class="avatar-title">
                                    <i class="bx bx-user-check font-size-24"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card mini-stats-wid">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted fw-medium">Total Records</p>
                            <h4 class="mb-0">{{ number_format($stats['total_records']) }}</h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="mini-stat-icon avatar-sm rounded-circle bg-warning">
                                <span class="avatar-title">
                                    <i class="bx bx-copy-alt font-size-24"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card mini-stats-wid">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted fw-medium">Transactions</p>
                            <h4 class="mb-0">{{ number_format($stats['transactions']) }}</h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="mini-stat-icon avatar-sm rounded-circle bg-info">
                                <span class="avatar-title">
                                    <i class="bx bx-purchase-tag-alt font-size-24"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8">
            <x-card title="Welcome">
                <p class="text-muted mb-3">
                    This starter is ready for the next module. Use the sidebar to manage users, roles, and application settings.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    @can('users.view')
                        <x-button href="{{ route('users.index') }}" icon="bx bx-user">Manage Users</x-button>
                    @endcan
                    @can('roles.view')
                        <x-button href="{{ route('roles.index') }}" variant="success" icon="bx bx-shield-quarter">Manage Roles</x-button>
                    @endcan
                    <x-button href="{{ route('settings.index') }}" variant="secondary" icon="bx bx-cog">Settings</x-button>
                </div>
            </x-card>
        </div>
        <div class="col-xl-4">
            <x-card title="Account">
                <p class="mb-1"><strong>Name:</strong> {{ auth()->user()->name }}</p>
                <p class="mb-1"><strong>Email:</strong> {{ auth()->user()->email }}</p>
                <p class="mb-0">
                    <strong>Role:</strong>
                    {{ auth()->user()->roles->pluck('name')->join(', ') ?: '-' }}
                </p>
            </x-card>
        </div>
    </div>
@endsection
