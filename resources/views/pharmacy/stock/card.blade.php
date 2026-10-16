@extends('layouts.app')

@section('title', 'Kartu Stok')

@section('content')
    <x-page-header title="Kartu Stok" :breadcrumb="['Farmasi' => route('pharmacy.stock.index'), 'Kartu Stok' => null]" />
    <x-alert />
    @include('pharmacy.partials.nav')
    <x-card title="Mutasi obat">
        <form method="GET" action="{{ route('pharmacy.stock.card') }}" class="mb-3" onsubmit="event.preventDefault(); const id = this.medicine_id.value; if (id) window.location = '{{ url('/pharmacy/stock/card') }}/' + id;">
            <label class="form-label" for="medicine_id">Obat</label>
            <select class="form-select" name="medicine_id" id="medicine_id" onchange="if (this.value) window.location='{{ url('/pharmacy/stock/card') }}/'+this.value">
                <option value="">Pilih obat</option>
                @foreach ($medicines as $row)
                    <option value="{{ $row->id }}" @selected($medicine && $medicine->id === $row->id)>{{ $row->catalogLabel() }}</option>
                @endforeach
            </select>
        </form>
        @if ($medicine)
            <div class="mb-3">
                <x-medicine-meta :medicine="$medicine" show-missing-kfa />
            </div>
            <x-table>
                <thead>
                    <tr><th>Waktu</th><th>Batch</th><th>Jenis</th><th>Qty</th><th>Catatan</th></tr>
                </thead>
                <tbody>
                    @forelse ($movements as $movement)
                        <tr>
                            <td>{{ $movement->created_at?->format('d M Y H:i') }}</td>
                            <td>{{ $movement->stock?->batch_number }}</td>
                            <td>{{ $movement->type->label() }}</td>
                            <td>{{ $movement->quantity }}</td>
                            <td>{{ $movement->notes }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted">Belum ada mutasi.</td></tr>
                    @endforelse
                </tbody>
            </x-table>
            @if (method_exists($movements, 'links'))
                <div class="mt-3">{{ $movements->links() }}</div>
            @endif
        @endif
    </x-card>
@endsection
