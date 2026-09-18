@extends('layouts.app')

@section('title', 'Hutang')

@section('content')
    <x-page-header title="Hutang Purchase Order" :breadcrumb="['Keuangan' => route('finance.payables'), 'Hutang' => null]" />
    <x-alert />
    <x-card title="PO diterima belum dibayar — {{ number_format((float) $total, 0, ',', '.') }}">
        <x-table>
            <thead>
                <tr>
                    <th>Nomor</th>
                    <th>Supplier</th>
                    <th>Cabang</th>
                    <th>Diterima</th>
                    <th class="text-end">Nilai</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td>{{ $order->number }}</td>
                        <td>{{ $order->supplier?->name }}</td>
                        <td>{{ $order->branch?->name }}</td>
                        <td>{{ $order->received_at?->format('d M Y') }}</td>
                        <td class="text-end">{{ number_format((float) $order->totalAmount(), 0, ',', '.') }}</td>
                        <td class="text-end">
                            @can('finance.manage')
                                <form method="POST" action="{{ route('finance.payables.pay', $order) }}" class="d-flex gap-2 justify-content-end">
                                    @csrf
                                    <select name="cash_account_id" class="form-select form-select-sm" required>
                                        <option value="">Pilih akun</option>
                                        @foreach ($accounts->where('branch_id', $order->branch_id) as $account)
                                            <option value="{{ $account->id }}">{{ $account->name }} ({{ number_format((float) $account->balance, 0, ',', '.') }})</option>
                                        @endforeach
                                    </select>
                                    <x-button type="submit" variant="success">Bayar</x-button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">Tidak ada hutang PO.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>
@endsection
