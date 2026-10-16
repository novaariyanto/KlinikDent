@extends('layouts.app')

@section('title', 'Settlement Sharing Fee')

@section('content')
    <x-page-header title="Settlement Sharing Fee" :breadcrumb="['Keuangan' => route('finance.index'), 'Sharing Fee' => null, 'Settlement' => null]" />
    <x-alert />

    @if (session('sharing_fee_exclusions'))
        <x-card title="Item dikecualikan (Belum memiliki aturan Sharing Fee)">
            <x-table>
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Item</th>
                        <th>Alasan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach (session('sharing_fee_exclusions') as $row)
                        <tr>
                            <td>{{ $row['invoice_number'] ?? '—' }}</td>
                            <td>{{ $row['description'] ?? '—' }}</td>
                            <td>{{ $row['reason'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </x-card>
    @endif

    @can('create', App\Models\SharingFeeSettlement::class)
        <x-card title="Generate settlement">
            <form method="POST" action="{{ route('finance.sharing-fees.settlements.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-2">
                    <x-select name="period_type" label="Periode" :options="$periodTypes" :selected="old('period_type', 'monthly')" required />
                </div>
                <div class="col-md-2">
                    <x-input name="period_start" label="Mulai" type="date" :value="old('period_start', now()->startOfMonth()->toDateString())" required />
                </div>
                <div class="col-md-2">
                    <x-input name="period_end" label="Akhir" type="date" :value="old('period_end', now()->endOfMonth()->toDateString())" required />
                </div>
                <div class="col-md-3">
                    <x-select
                        name="branch_id"
                        label="Cabang"
                        :options="$branches->mapWithKeys(fn ($b) => [$b->id => $b->name])->all()"
                        :selected="old('branch_id')"
                        placeholder="Semua cabang"
                    />
                </div>
                <div class="col-md-3">
                    <x-select
                        name="doctor_user_id"
                        label="Dokter"
                        :options="$doctors->mapWithKeys(fn ($d) => [$d->id => $d->name])->all()"
                        :selected="old('doctor_user_id')"
                        placeholder="Semua dokter"
                    />
                </div>
                <div class="col-12">
                    <x-button type="submit" icon="bx bx-calculator">Generate dari invoice lunas</x-button>
                </div>
            </form>
        </x-card>
    @endcan

    <x-card title="Daftar settlement">
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-2">
                <x-select name="status" label="Status" :options="$statuses" :selected="request('status')" placeholder="Semua" />
            </div>
            <div class="col-md-3">
                <x-select
                    name="doctor_user_id"
                    label="Dokter"
                    :options="$doctors->mapWithKeys(fn ($d) => [$d->id => $d->name])->all()"
                    :selected="request('doctor_user_id')"
                    placeholder="Semua"
                />
            </div>
            <div class="col-md-2">
                <label class="form-label" for="date_from">Dari</label>
                <input id="date_from" type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="date_to">Sampai</label>
                <input id="date_to" type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
            </div>
            <div class="col-md-2">
                <x-button type="submit" icon="bx bx-search">Filter</x-button>
            </div>
        </form>

        <x-table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Dokter</th>
                    <th>Cabang</th>
                    <th>Periode</th>
                    <th>Status</th>
                    <th class="text-end">Net Fee</th>
                    <th class="text-end">Dibayar</th>
                    <th class="text-end">Sisa</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($settlements as $settlement)
                    <tr>
                        <td>{{ $settlement->id }}</td>
                        <td>{{ $settlement->doctor?->name }}</td>
                        <td>{{ $settlement->branch?->name ?? '—' }}</td>
                        <td>
                            {{ $settlement->period_type?->label() }}<br>
                            <small class="text-muted">{{ $settlement->period_start?->format('d M Y') }} – {{ $settlement->period_end?->format('d M Y') }}</small>
                        </td>
                        <td><span class="{{ $settlement->status?->badgeClass() }}">{{ $settlement->status?->label() }}</span></td>
                        <td class="text-end">{{ number_format((float) $settlement->net_fee_amount, 0, ',', '.') }}</td>
                        <td class="text-end">{{ number_format((float) $settlement->paid_amount, 0, ',', '.') }}</td>
                        <td class="text-end">{{ number_format((float) $settlement->outstandingAmount(), 0, ',', '.') }}</td>
                        <td class="text-end">
                            <a href="{{ route('finance.sharing-fees.settlements.show', $settlement) }}" class="btn btn-sm btn-soft-primary">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-muted">Belum ada settlement. Generate dari invoice lunas di atas.</td></tr>
                @endforelse
            </tbody>
        </x-table>

        @if ($settlements->hasPages())
            <div class="mt-3">{{ $settlements->links() }}</div>
        @endif
    </x-card>
@endsection
