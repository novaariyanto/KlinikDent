@extends('layouts.app')

@section('title', 'Promosi')

@section('content')
    <x-page-header title="Promosi" :breadcrumb="['WhatsApp Otomatis' => route('whatsapp.dashboard'), 'Promosi' => null]">
        @if ($canManage)
            <x-button href="{{ route('whatsapp.promotions.create') }}" icon="bx bx-plus">Buat promo</x-button>
        @endif
    </x-page-header>
    <x-alert />
    @include('whatsapp.partials.nav')

    <x-card title="Promosi klinik">
        <p class="text-muted">Hanya pasien dengan persetujuan komunikasi marketing yang dapat menerima promo.</p>
        <x-table>
            <thead>
                <tr>
                    <th>Judul</th>
                    <th>Cabang</th>
                    <th>Periode</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($promotions as $promotion)
                    <tr>
                        <td>{{ $promotion->title }}</td>
                        <td>{{ $promotion->branch?->name ?: 'Semua cabang (pasien eligible)' }}</td>
                        <td>{{ $promotion->starts_at?->format('d M Y') }} — {{ $promotion->ends_at?->format('d M Y') }}</td>
                        <td>
                            @if ($promotion->is_enabled)
                                <span class="badge bg-success">Aktif</span>
                            @else
                                <span class="badge bg-secondary">Nonaktif</span>
                            @endif
                            @if ($promotion->dispatched_at)
                                <span class="badge bg-info">Sudah dikirim</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @if ($canManage)
                                <x-button href="{{ route('whatsapp.promotions.edit', $promotion) }}" variant="secondary">Edit</x-button>
                                <form method="POST" action="{{ route('whatsapp.promotions.send', $promotion) }}" class="d-inline">
                                    @csrf
                                    <x-button type="submit" variant="success">Kirim ke pasien eligible</x-button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">Belum ada promo.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        {{ $promotions->links() }}
    </x-card>
@endsection
