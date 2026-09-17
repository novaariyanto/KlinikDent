@extends('layouts.app')

@section('title', $service->name)

@section('content')
    <x-page-header title="{{ $service->name }}" :breadcrumb="['Layanan' => route('management.services.index'), $service->name => null]" />

    <x-alert />

    <div class="row">
        <div class="col-lg-8">
            <x-card title="Detail Layanan">
                <x-table>
                    <tbody>
                        <tr>
                            <th width="180">Nama</th>
                            <td>{{ $service->name }}</td>
                        </tr>
                        <tr>
                            <th>Kategori</th>
                            <td>{{ $service->categoryLabel() }}</td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <span class="{{ $service->is_active ? 'badge bg-success' : 'badge bg-danger' }}">
                                    {{ $service->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </x-table>

                <div class="mt-3 d-flex flex-wrap gap-2">
                    @can('update', $service)
                        <x-button href="{{ route('management.services.edit', $service) }}" icon="bx bx-edit">Edit</x-button>
                    @endcan
                    <x-button href="{{ route('management.services.index') }}" variant="secondary">Back</x-button>
                </div>
            </x-card>
        </div>
    </div>
@endsection
