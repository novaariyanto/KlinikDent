@extends('layouts.app')

@section('title', $medicine->name)

@section('content')
    <x-page-header title="{{ $medicine->name }}" :breadcrumb="['Obat' => route('management.medicines.index'), $medicine->name => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Detail Obat">
                <x-table>
                    <tbody>
                        <tr><th width="180">Nama</th><td>{{ $medicine->name }}</td></tr>
                        <tr><th>Satuan</th><td>{{ $medicine->unitLabel() }}</td></tr>
                        <tr><th>Kategori</th><td>{{ $medicine->categoryLabel() }}</td></tr>
                        <tr><th>Harga Dasar</th><td>Rp {{ number_format((float) $medicine->base_price, 0, ',', '.') }}</td></tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <span class="{{ $medicine->is_active ? 'badge bg-success' : 'badge bg-danger' }}">
                                    {{ $medicine->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </x-table>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    @can('update', $medicine)
                        <x-button href="{{ route('management.medicines.edit', $medicine) }}" icon="bx bx-edit">Edit</x-button>
                    @endcan
                    <x-button href="{{ route('management.medicines.index') }}" variant="secondary">Back</x-button>
                </div>
            </x-card>
        </div>
    </div>
@endsection
