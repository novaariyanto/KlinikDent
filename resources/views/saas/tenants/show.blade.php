@extends('layouts.app')

@section('title', $tenant->name)

@section('content')
    <x-page-header title="{{ $tenant->name }}" :breadcrumb="['Semua Klinik' => route('saas.tenants.index'), $tenant->name => null]" />

    <x-alert />

    <div class="row">
        <div class="col-lg-7">
            <x-card title="Detail Klinik">
                <x-table>
                    <tbody>
                        <tr>
                            <th width="180">Nama</th>
                            <td>{{ $tenant->name }}</td>
                        </tr>
                        <tr>
                            <th>Subdomain</th>
                            <td><code>{{ $tenant->subdomain }}</code></td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <span class="{{ $tenant->status->badgeClass() }}">{{ $tenant->status->label() }}</span>
                            </td>
                        </tr>
                        <tr>
                            <th>Created At</th>
                            <td>{{ $tenant->created_at?->format('d M Y H:i') }}</td>
                        </tr>
                    </tbody>
                </x-table>

                <div class="mt-3 d-flex flex-wrap gap-2">
                    @can('tenant.update')
                        <x-button href="{{ route('saas.tenants.edit', $tenant) }}" icon="bx bx-edit">Edit</x-button>
                    @endcan
                    @can('toggleStatus', $tenant)
                        <form action="{{ route('saas.tenants.status', $tenant) }}" method="POST">
                            @csrf
                            <x-button type="submit" variant="warning" icon="bx bx-refresh">
                                {{ $tenant->status === \App\Enums\TenantStatus::Active ? 'Suspend' : 'Aktifkan' }}
                            </x-button>
                        </form>
                    @endcan
                    <x-button href="{{ route('saas.tenants.index') }}" variant="secondary">Back</x-button>
                </div>
            </x-card>
        </div>
        <div class="col-lg-5">
            <x-card title="Cabang">
                @forelse ($tenant->branches as $branch)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>{{ $branch->name }}</span>
                        <span class="{{ $branch->is_active ? 'badge bg-success' : 'badge bg-danger' }}">
                            {{ $branch->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>
                @empty
                    <p class="text-muted mb-0">Belum ada cabang.</p>
                @endforelse
            </x-card>
            <x-card title="User">
                @forelse ($tenant->users as $user)
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <span>{{ $user->name }}</span>
                        <span class="text-muted">{{ $user->roles->pluck('name')->join(', ') ?: '-' }}</span>
                    </div>
                @empty
                    <p class="text-muted mb-0">Belum ada user.</p>
                @endforelse
            </x-card>
        </div>
    </div>
@endsection
