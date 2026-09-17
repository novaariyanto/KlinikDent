@extends('layouts.app')

@section('title', 'User Detail')

@section('content')
    <x-page-header title="User Detail" :breadcrumb="['Users' => route('users.index'), $user->name => null]" />

    <x-alert />

    <div class="row">
        <div class="col-lg-8">
            <x-card title="Profile">
                <x-table>
                    <tbody>
                        <tr>
                            <th width="180">Name</th>
                            <td>{{ $user->name }}</td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td>{{ $user->email }}</td>
                        </tr>
                        <tr>
                            <th>Role</th>
                            <td>
                                @forelse ($user->roles as $role)
                                    <span class="badge badge-soft-primary">{{ $role->name }}</span>
                                @empty
                                    <span class="text-muted">-</span>
                                @endforelse
                            </td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <span class="{{ $user->status->badgeClass() }}">{{ $user->status->label() }}</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Created At</th>
                            <td>{{ $user->created_at?->format('d M Y H:i') }}</td>
                        </tr>
                    </tbody>
                </x-table>

                <div class="mt-3 d-flex flex-wrap gap-2">
                    @can('users.edit')
                        <x-button href="{{ route('users.edit', $user) }}" icon="bx bx-edit">Edit</x-button>
                        <x-button variant="warning" data-bs-toggle="modal" data-bs-target="#reset-password-modal" icon="bx bx-key">
                            Reset Password
                        </x-button>
                    @endcan
                    @can('impersonate', $user)
                        <form action="{{ route('users.impersonate', $user) }}" method="POST">
                            @csrf
                            <x-button type="submit" variant="info" icon="bx bx-user-check">Impersonate</x-button>
                        </form>
                    @endcan
                    <x-button href="{{ route('users.index') }}" variant="secondary">Back</x-button>
                </div>
            </x-card>
        </div>
    </div>

    @can('users.edit')
        <x-modal id="reset-password-modal" title="Reset Password">
            <form method="POST" action="{{ route('users.reset-password', $user) }}" id="reset-password-form">
                @csrf
                <x-input name="password" type="password" label="New Password" required />
                <x-input name="password_confirmation" type="password" label="Confirm Password" required />
            </form>
            <x-slot:footer>
                <x-button variant="secondary" data-bs-dismiss="modal">Cancel</x-button>
                <x-button type="submit" variant="warning" form="reset-password-form">Reset Password</x-button>
            </x-slot:footer>
        </x-modal>
    @endcan
@endsection
