@extends('layouts.app')

@section('title', $payer->name)

@section('content')
    <x-page-header title="{{ $payer->name }}" :breadcrumb="['Penjamin' => route('management.payers.index'), $payer->name => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Detail Penjamin">
                <x-table>
                    <tbody>
                        <tr><th width="180">Nama</th><td>{{ $payer->name }}</td></tr>
                        <tr><th>Tipe</th><td>{{ $payer->type->label() }}</td></tr>
                        <tr><th>No. Kontrak</th><td>{{ $payer->contract_number ?: '-' }}</td></tr>
                    </tbody>
                </x-table>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    @can('update', $payer)
                        <x-button href="{{ route('management.payers.edit', $payer) }}" icon="bx bx-edit">Edit</x-button>
                    @endcan
                    <x-button href="{{ route('management.payers.index') }}" variant="secondary">Back</x-button>
                </div>
            </x-card>
        </div>
    </div>
@endsection
