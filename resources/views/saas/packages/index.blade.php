@extends('layouts.app')

@section('title', 'Paket')

@section('content')
    <x-page-header title="Paket Langganan" :breadcrumb="['Subscription' => null, 'Paket' => route('saas.packages.index')]" />
    <x-alert />

    @can('create', App\Models\SaasPackage::class)
        <x-card title="Paket baru">
            <form method="POST" action="{{ route('saas.packages.store') }}">
                @csrf
                @include('saas.packages.partials.fields', ['formKey' => 'create'])
                <x-button type="submit" icon="bx bx-plus">Simpan</x-button>
            </form>
        </x-card>
    @endcan

    <x-card title="Daftar paket">
        <x-table>
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Harga</th>
                    <th>Periode</th>
                    <th>Trial</th>
                    <th>Cabang</th>
                    <th>Pengguna</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($packages as $package)
                    <tr>
                        <td>{{ $package->name }}</td>
                        <td>Rp {{ number_format((float) $package->price, 0, ',', '.') }}</td>
                        <td>{{ $package->interval->label() }}</td>
                        <td>{{ $package->trial_days }} hari</td>
                        <td>{{ $package->max_branches ?: 'Tidak dibatasi' }}</td>
                        <td>{{ $package->max_users ?: 'Tidak dibatasi' }}</td>
                        <td>
                            <span class="badge {{ $package->is_active ? 'bg-success' : 'bg-secondary' }}">
                                {{ $package->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="text-end">
                            @can('update', $package)
                                <div class="d-flex justify-content-end gap-1 flex-wrap">
                                    <x-button variant="soft-secondary" data-bs-toggle="modal" data-bs-target="#edit-package-{{ $package->id }}" icon="bx bx-edit">Ubah</x-button>
                                    <form method="POST" action="{{ route('saas.packages.status', $package) }}">
                                        @csrf
                                        <x-button type="submit" variant="{{ $package->is_active ? 'soft-warning' : 'soft-success' }}">
                                            {{ $package->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </x-button>
                                    </form>
                                    @if ($package->subscriptions_count === 0)
                                        <form method="POST" action="{{ route('saas.packages.destroy', $package) }}" onsubmit="return confirm('Hapus paket ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <x-button type="submit" variant="soft-danger" icon="bx bx-trash">Hapus</x-button>
                                        </form>
                                    @endif
                                </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">Belum ada paket.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    @can('create', App\Models\SaasPackage::class)
        @foreach ($packages as $package)
            <x-modal id="edit-package-{{ $package->id }}" title="Ubah {{ $package->name }}" size="modal-lg">
                <form method="POST" action="{{ route('saas.packages.update', $package) }}">
                    @csrf
                    @method('PUT')
                    @include('saas.packages.partials.fields', ['package' => $package, 'formKey' => 'edit-'.$package->id])
                    <x-button type="submit" icon="bx bx-save">Simpan perubahan</x-button>
                </form>
            </x-modal>
        @endforeach
    @endcan
@endsection

@push('scripts')
    @if ($errors->any() && str_starts_with((string) old('_form'), 'edit-'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var modal = document.getElementById(@json('edit-package-'.substr((string) old('_form'), 5)));
                if (modal && window.bootstrap) {
                    bootstrap.Modal.getOrCreateInstance(modal).show();
                }
            });
        </script>
    @endif
@endpush
