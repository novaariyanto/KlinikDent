@extends('layouts.app')

@section('title', 'Invoice Platform')

@section('content')
    <x-page-header title="Invoice Langganan" :breadcrumb="['Subscription' => null, 'Invoice' => route('saas.invoices.index')]" />
    <x-alert />

    <x-card title="Daftar invoice">
        <x-table>
            <thead>
                <tr>
                    <th>Nomor</th>
                    <th>Klinik</th>
                    <th>Paket</th>
                    <th class="text-end">Nominal</th>
                    <th>Status</th>
                    <th>Jatuh tempo</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    <tr>
                        <td>{{ $invoice->number }}</td>
                        <td>{{ $invoice->tenant?->name }}</td>
                        <td>{{ $invoice->subscription?->package?->name }}</td>
                        <td class="text-end">{{ number_format((float) $invoice->amount, 0, ',', '.') }}</td>
                        <td><span class="{{ $invoice->status->badgeClass() }}">{{ $invoice->status->label() }}</span></td>
                        <td>{{ $invoice->due_at?->format('d M Y') }}</td>
                        <td>
                            @if ($invoice->status === \App\Enums\SaasInvoiceStatus::Unpaid)
                                @can('update', $invoice)
                                    <form method="POST" action="{{ route('saas.invoices.pay', $invoice) }}">
                                        @csrf
                                        <x-button type="submit">Tandai lunas</x-button>
                                    </form>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">Belum ada invoice.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        {{ $invoices->links() }}
    </x-card>
@endsection
