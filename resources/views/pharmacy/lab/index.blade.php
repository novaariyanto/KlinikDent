@extends('layouts.app')

@section('title', 'Laboratorium Gigi')

@section('content')
    <x-page-header title="Pesanan Laboratorium Gigi" :breadcrumb="['Farmasi' => route('pharmacy.index'), 'Lab gigi' => null]" />
    <x-alert />
    @include('pharmacy.partials.nav')
    <x-card title="Daftar kerja">
        <x-table>
            <thead>
                <tr><th>Pasien</th><th>Jenis</th><th>Gigi</th><th>Warna</th><th>Vendor</th><th>Jatuh tempo</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td>{{ $order->visit?->patient?->name }}</td>
                        <td>{{ $order->work_type }}</td>
                        <td>{{ $order->tooth_numbers ?: '-' }}</td>
                        <td>{{ $order->shade ?: '-' }}</td>
                        <td>{{ $order->vendor ?: '-' }}</td>
                        <td>{{ $order->due_date?->format('d M Y') ?: '-' }}</td>
                        <td>
                            <form method="POST" action="{{ route('pharmacy.lab.update', $order) }}" class="d-flex gap-2">
                                @csrf
                                @method('PUT')
                                <select class="form-select form-select-sm" name="status">
                                    @foreach ($statuses as $status)
                                        <option value="{{ $status->value }}" @selected($order->status === $status)>{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                                <button class="btn btn-sm btn-primary" type="submit">Simpan</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">Belum ada pesanan.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        <div class="mt-3">{{ $orders->links() }}</div>
    </x-card>
@endsection
