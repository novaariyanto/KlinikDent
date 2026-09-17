@extends('layouts.app')

@section('title', 'Tarif')

@section('content')
    <x-page-header title="Tarif" :breadcrumb="['Master Data' => route('management.clinic'), 'Tarif' => route('management.tariffs.index')]" />
    <x-alert />

    <x-card title="Matriks Tarif">
        <form method="GET" action="{{ route('management.tariffs.index') }}" class="row g-2 mb-3">
            <div class="col-md-4">
                <select name="branch_id" class="form-select" onchange="this.form.submit()">
                    <option value="">Semua / default cabang</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) $branchId === (int) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle">
                <thead>
                    <tr>
                        <th>Tindakan</th>
                        <th class="text-end">Default</th>
                        @foreach ($payers as $payer)
                            <th class="text-end">{{ $payer->name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($procedures as $procedure)
                        <tr>
                            <td>{{ $procedure->name }}</td>
                            <td class="text-end">
                                @php $cell = $matrix[$procedure->id.'|default'] ?? null; @endphp
                                {{ $cell ? 'Rp '.number_format((float) $cell['price'], 0, ',', '.') : '-' }}
                            </td>
                            @foreach ($payers as $payer)
                                <td class="text-end">
                                    @php $cell = $matrix[$procedure->id.'|'.$payer->id] ?? null; @endphp
                                    {{ $cell ? 'Rp '.number_format((float) $cell['price'], 0, ',', '.') : '-' }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 2 + $payers->count() }}" class="text-muted">Belum ada tindakan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card title="Daftar Tarif" class="mt-4">
        <x-slot:header-actions>
            @can('create', App\Models\Tariff::class)
                <x-button href="{{ route('management.tariffs.create') }}" icon="bx bx-plus">Tambah Tarif</x-button>
            @endcan
        </x-slot:header-actions>
        <x-data-table id="tariffs-table" :ajax="route('management.tariffs.data')" :columns="$columns" :order="[[5, 'desc']]" />
    </x-card>
@endsection
