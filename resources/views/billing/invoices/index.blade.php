@extends('layouts.app')

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumb="['Billing' => route('billing.invoices'), $title => null]" />
    <x-alert />

    @if ($showUnbilled && $unbilled->isNotEmpty())
        <x-card title="Kunjungan belum ditagih">
            <x-table>
                <thead>
                    <tr>
                        <th>Pasien</th>
                        <th>Cabang</th>
                        <th>Tanggal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($unbilled as $visit)
                        <tr>
                            <td>{{ $visit->patient?->name }}</td>
                            <td>{{ $visit->branch?->name }}</td>
                            <td>{{ $visit->visit_date?->format('d M Y') }}</td>
                            <td class="text-end">
                                @can('create', App\Models\Invoice::class)
                                    <form method="POST" action="{{ route('billing.invoices.generate') }}">
                                        @csrf
                                        <input type="hidden" name="visit_id" value="{{ $visit->id }}">
                                        <x-button type="submit" variant="success">Buat tagihan</x-button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </x-card>
    @endif

    <x-card :title="$title">
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label" for="q">Cari nomor / pasien</label>
                <input id="q" type="text" name="q" value="{{ request('q') }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">Semua</option>
                    @foreach (\App\Enums\InvoiceStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <x-button type="submit" icon="bx bx-search">Filter</x-button>
            </div>
        </form>
        <x-table>
            <thead>
                <tr>
                    <th>Nomor</th>
                    <th>Pasien</th>
                    <th>Penjamin</th>
                    <th>Total</th>
                    <th>Sisa</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr>
                        <td>{{ $invoice->number }}</td>
                        <td>{{ $invoice->patient?->name }}</td>
                        <td>{{ $invoice->payer?->name ?: '-' }}</td>
                        <td>{{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</td>
                        <td>{{ number_format((float) $invoice->remainingAmount(), 0, ',', '.') }}</td>
                        <td><span class="{{ $invoice->status->badgeClass() }}">{{ $invoice->status->label() }}</span></td>
                        <td class="text-end">
                            <x-button href="{{ route('billing.invoices.show', $invoice) }}" variant="light">Buka</x-button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">Belum ada tagihan.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        @if ($invoices->hasPages())
            <div class="mt-3">{{ $invoices->links() }}</div>
        @endif
    </x-card>
@endsection
