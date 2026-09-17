@extends('layouts.app')

@section('title', $room->name)

@section('content')
    <x-page-header title="{{ $room->name }}" :breadcrumb="['Ruangan' => route('management.rooms.index'), $room->name => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Detail Ruangan">
                <x-table>
                    <tbody>
                        <tr><th width="180">Nama</th><td>{{ $room->name }}</td></tr>
                        <tr><th>Cabang</th><td>{{ $room->branch?->name ?: '-' }}</td></tr>
                        <tr><th>Tipe</th><td>{{ $room->type->label() }}</td></tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <span class="{{ $room->is_active ? 'badge bg-success' : 'badge bg-danger' }}">
                                    {{ $room->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </x-table>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    @can('update', $room)
                        <x-button href="{{ route('management.rooms.edit', $room) }}" icon="bx bx-edit">Edit</x-button>
                    @endcan
                    <x-button href="{{ route('management.rooms.index') }}" variant="secondary">Back</x-button>
                </div>
            </x-card>
        </div>
    </div>
@endsection
