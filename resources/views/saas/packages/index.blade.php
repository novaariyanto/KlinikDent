@extends('layouts.app')

@section('title', 'Paket')

@section('content')
    <x-page-header title="Paket Langganan" :breadcrumb="['Subscription' => null, 'Paket' => route('saas.packages.index')]" />
    <x-alert />

    @can('create', App\Models\SaasPackage::class)
        <x-card title="Paket baru">
            <form method="POST" action="{{ route('saas.packages.store') }}" class="row g-2">
                @csrf
                <div class="col-md-3">
                    <x-input name="name" label="Nama" :value="old('name')" required />
                </div>
                <div class="col-md-2">
                    <x-input name="price" label="Harga" type="number" :value="old('price', 0)" required />
                </div>
                <div class="col-md-2">
                    <x-select name="interval" label="Periode" :options="\App\Http\Controllers\Saas\PackageController::intervalOptions()" :selected="old('interval', 'monthly')" required />
                </div>
                <div class="col-md-2">
                    <x-input name="trial_days" label="Hari trial" type="number" :value="old('trial_days', 14)" required />
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <x-button type="submit" icon="bx bx-plus">Simpan</x-button>
                </div>
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
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($packages as $package)
                    <tr>
                        <td>{{ $package->name }}</td>
                        <td>{{ number_format((float) $package->price, 0, ',', '.') }}</td>
                        <td>{{ $package->interval->label() }}</td>
                        <td>{{ $package->trial_days }} hari</td>
                        <td>{{ $package->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                        <td>
                            @can('delete', $package)
                                <form method="POST" action="{{ route('saas.packages.destroy', $package) }}" onsubmit="return confirm('Hapus paket ini?')">
                                    @csrf
                                    @method('DELETE')
                        <x-button type="submit" variant="danger">Hapus</x-button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">Belum ada paket.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>
@endsection
