@extends('layouts.app')

@section('title', $doctor->displayName())

@section('content')
    <x-page-header title="{{ $doctor->displayName() }}" :breadcrumb="['Tenaga Medis' => route('doctors.index'), $doctor->displayName() => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-5">
            <x-card title="Profil">
                <x-table>
                    <tbody>
                        <tr><th width="160">Nama</th><td>{{ $doctor->displayName() }}</td></tr>
                        <tr><th>Email</th><td>{{ $doctor->user?->email ?: '-' }}</td></tr>
                        <tr><th>Peran</th><td><span class="badge badge-soft-primary">{{ $doctor->roleLabel() }}</span></td></tr>
                        <tr><th>Spesialisasi</th><td>{{ $doctor->specialization ?: '-' }}</td></tr>
                        <tr><th>SIP</th><td>{{ $doctor->sip ?: '-' }}</td></tr>
                        <tr><th>STR</th><td>{{ $doctor->str ?: '-' }}</td></tr>
                        <tr><th>Telepon</th><td>{{ $doctor->phone ?: '-' }}</td></tr>
                        <tr>
                            <th>Cabang</th>
                            <td>
                                @php
                                    $names = $doctor->user?->branches->pluck('name') ?? collect();
                                    if ($names->isEmpty() && $doctor->user?->branch) {
                                        $names = collect([$doctor->user->branch->name]);
                                    }
                                @endphp
                                {{ $names->implode(', ') ?: '-' }}
                            </td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <span class="{{ $doctor->is_active ? 'badge bg-success' : 'badge bg-danger' }}">
                                    {{ $doctor->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                        </tr>
                        @if ($doctor->notes)
                            <tr><th>Catatan</th><td>{{ $doctor->notes }}</td></tr>
                        @endif
                    </tbody>
                </x-table>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    @can('update', $doctor)
                        <x-button href="{{ route('doctors.edit', $doctor) }}" icon="bx bx-edit">Edit</x-button>
                    @endcan
                    @can('users.view')
                        @if ($doctor->user)
                            <x-button href="{{ route('users.show', $doctor->user) }}" variant="secondary" icon="bx bx-user">Akun</x-button>
                        @endif
                    @endcan
                    <x-button href="{{ route('doctors.index') }}" variant="secondary">Back</x-button>
                </div>
            </x-card>
        </div>
        <div class="col-lg-7">
            <x-card title="Jadwal Praktik">
                @can('create', App\Models\DoctorSchedule::class)
                    <form method="POST" action="{{ route('doctors.schedules.store', $doctor) }}" class="mb-4" novalidate>
                        @csrf
                        @include('doctors.partials.schedule-form')
                        <x-button type="submit" icon="bx bx-plus">Tambah Jadwal</x-button>
                    </form>
                @endcan

                <x-table>
                    <thead>
                        <tr>
                            <th>Hari</th>
                            <th>Jam</th>
                            <th>Cabang</th>
                            <th>Poli</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($doctor->schedules as $item)
                            <tr>
                                <td>{{ $item->weekday->label() }}</td>
                                <td>{{ $item->timeRange() }}</td>
                                <td>{{ $item->branch?->name ?: '-' }}</td>
                                <td>{{ $item->room?->name ?: '-' }}</td>
                                <td>
                                    <span class="{{ $item->is_active ? 'badge bg-success' : 'badge bg-danger' }}">
                                        {{ $item->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    @can('update', $item)
                                        <a href="{{ route('doctors.schedules.edit', $item) }}" class="btn btn-sm btn-soft-secondary">Edit</a>
                                    @endcan
                                    @can('delete', $item)
                                        <form action="{{ route('doctors.schedules.destroy', $item) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus jadwal ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-soft-danger">Hapus</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-muted">Belum ada jadwal praktik.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-table>
            </x-card>
        </div>
    </div>
@endsection
