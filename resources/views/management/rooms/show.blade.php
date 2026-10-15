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
                        <tr><th>Kode internal</th><td>{{ $room->code ?: '-' }}</td></tr>
                        <tr><th>Deskripsi</th><td>{{ $room->description ?: '-' }}</td></tr>
                        <tr><th>Cabang</th><td>{{ $room->branch?->name ?: '-' }}</td></tr>
                        <tr><th>Telepon</th><td>{{ $room->phone ?: '-' }}</td></tr>
                        <tr><th>Email</th><td>{{ $room->email ?: '-' }}</td></tr>
                        <tr><th>Tipe</th><td>{{ $room->type->label() }}</td></tr>
                        <tr>
                            <th>IHS</th>
                            <td>
                                @if ($room->type === \App\Enums\RoomType::Poli)
                                    {{ $room->satusehatLocation?->satusehat_id ?: '-' }}
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
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
                        @if ($room->type === \App\Enums\RoomType::Poli && ! filled($room->satusehatLocation?->satusehat_id))
                            <form action="{{ route('management.rooms.satusehat', $room) }}" method="POST">
                                @csrf
                                <x-button type="submit" variant="secondary" icon="bx bx-cloud-download">Ambil IHS</x-button>
                            </form>
                        @endif
                    @endcan
                    <x-button href="{{ route('management.rooms.index') }}" variant="secondary">Back</x-button>
                </div>
            </x-card>
        </div>
    </div>
@endsection
