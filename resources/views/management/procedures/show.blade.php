@extends('layouts.app')

@section('title', $procedure->name)

@section('content')
    <x-page-header title="{{ $procedure->name }}" :breadcrumb="['Tindakan' => route('management.procedures.index'), $procedure->name => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Detail Tindakan">
                <x-table>
                    <tbody>
                        <tr><th width="180">Kode</th><td>{{ $procedure->code }}</td></tr>
                        <tr><th>Nama</th><td>{{ $procedure->name }}</td></tr>
                        <tr><th>Layanan</th><td>{{ $procedure->service?->name ?: '-' }}</td></tr>
                        <tr><th>Kategori</th><td>{{ $procedure->categoryLabel() }}</td></tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <span class="{{ $procedure->is_active ? 'badge bg-success' : 'badge bg-danger' }}">
                                    {{ $procedure->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </x-table>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    @can('update', $procedure)
                        <x-button href="{{ route('management.procedures.edit', $procedure) }}" icon="bx bx-edit">Edit</x-button>
                    @endcan
                    <x-button href="{{ route('management.procedures.index') }}" variant="secondary">Back</x-button>
                </div>
            </x-card>
        </div>
    </div>
@endsection
