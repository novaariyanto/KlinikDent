@extends('layouts.app')

@section('title', 'Kebijakan Pembebasan Jasa Dokter')

@section('content')
    <x-page-header title="Kebijakan Pembebasan Jasa Dokter" :breadcrumb="['Keuangan' => route('finance.index'), 'Pembebasan Jasa Dokter' => null]" />
    <x-alert />

    <x-card title="Dokter yang diizinkan">
        <x-slot:header-actions>
            @can('create', App\Models\DoctorFeeWaiverSetting::class)
                <x-button href="{{ route('finance.doctor-fee-waivers.settings.create') }}" icon="bx bx-plus">Tambah kebijakan</x-button>
            @endcan
        </x-slot:header-actions>

        <p class="text-muted small mb-3">
            Hanya dokter pada daftar ini yang dapat membebaskan jasa dokter saat input tindakan.
            Jasa dokter yang dibebaskan tidak masuk utang sharing fee kecuali opsi tersebut diaktifkan.
        </p>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <x-select
                    name="doctor_user_id"
                    label="Dokter"
                    :options="$doctors->mapWithKeys(fn ($d) => [$d->id => $d->name])->all()"
                    :selected="request('doctor_user_id')"
                    placeholder="Semua dokter"
                />
            </div>
            <div class="col-md-3">
                <x-button type="submit" icon="bx bx-search">Filter</x-button>
            </div>
        </form>

        <x-table>
            <thead>
                <tr>
                    <th>Dokter</th>
                    <th>Cabang</th>
                    <th>Diizinkan</th>
                    <th>Perlu persetujuan</th>
                    <th>Sharing fee tetap dihitung</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($settings as $setting)
                    <tr>
                        <td>{{ $setting->doctor?->name ?: '-' }}</td>
                        <td>{{ $setting->branch?->name ?: 'Semua cabang' }}</td>
                        <td>
                            <span class="badge {{ $setting->is_allowed ? 'bg-success' : 'bg-secondary' }}">
                                {{ $setting->is_allowed ? 'Ya' : 'Tidak' }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ $setting->require_approval ? 'bg-warning' : 'bg-secondary' }}">
                                {{ $setting->require_approval ? 'Ya' : 'Tidak' }}
                            </span>
                        </td>
                        <td>
                            <span class="badge {{ $setting->include_waived_in_sharing_fee ? 'bg-info' : 'bg-secondary' }}">
                                {{ $setting->include_waived_in_sharing_fee ? 'Ya' : 'Tidak' }}
                            </span>
                        </td>
                        <td class="text-end text-nowrap">
                            @can('update', $setting)
                                <a href="{{ route('finance.doctor-fee-waivers.settings.edit', $setting) }}" class="btn btn-sm btn-secondary">Edit</a>
                            @endcan
                            @can('delete', $setting)
                                <form action="{{ route('finance.doctor-fee-waivers.settings.destroy', $setting) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus kebijakan ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-danger" type="submit">Hapus</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">Belum ada kebijakan. Dokter belum dapat membebaskan jasa dokter.</td></tr>
                @endforelse
            </tbody>
        </x-table>

        <div class="mt-3">{{ $settings->links() }}</div>
    </x-card>
@endsection
