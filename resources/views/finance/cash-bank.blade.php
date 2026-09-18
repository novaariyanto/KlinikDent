@extends('layouts.app')

@section('title', 'Kas & Bank')

@section('content')
    <x-page-header title="Kas & Bank" :breadcrumb="['Keuangan' => route('finance.cash-bank'), 'Kas & Bank' => null]" />
    <x-alert />

    <div class="row">
        @foreach ($accounts as $account)
            <div class="col-md-4">
                <x-card :title="$account->name">
                    <p class="text-muted mb-1">{{ $account->type->label() }} — {{ $account->branch?->name }}</p>
                    <h3 class="mb-2">{{ number_format((float) $account->balance, 0, ',', '.') }}</h3>
                    <a href="{{ route('finance.cash-bank', ['cash_account_id' => $account->id]) }}">Lihat mutasi</a>
                </x-card>
            </div>
        @endforeach
    </div>

    <x-card title="Total saldo {{ number_format((float) $totalBalance, 0, ',', '.') }}">
        @if ($selected)
            <h5 class="mb-3">Mutasi {{ $selected->name }}</h5>
            <x-table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Tipe</th>
                        <th>Keterangan</th>
                        <th class="text-end">Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($mutations as $mutation)
                        <tr>
                            <td>{{ $mutation->mutated_at?->format('d M Y H:i') }}</td>
                            <td><span class="{{ $mutation->type->badgeClass() }}">{{ $mutation->type->label() }}</span></td>
                            <td>{{ $mutation->notes ?: '-' }}</td>
                            <td class="text-end">{{ number_format((float) $mutation->signedAmount(), 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">Belum ada mutasi.</td></tr>
                    @endforelse
                </tbody>
            </x-table>
            @if (method_exists($mutations, 'hasPages') && $mutations->hasPages())
                <div class="mt-3">{{ $mutations->links() }}</div>
            @endif
        @else
            <p class="text-muted mb-0">Belum ada akun kas/bank. Akun default dibuat otomatis saat pembayaran pertama.</p>
        @endif
    </x-card>
@endsection
