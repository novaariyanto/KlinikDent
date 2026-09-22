@extends('layouts.app')

@section('title', 'Paket Langganan')

@section('content')
    <x-page-header title="Paket Langganan" :breadcrumb="['Langganan' => null, 'Paket' => route('subscription.index')]" />
    <x-alert />

    <x-card title="Paket yang digunakan">
        @if ($subscription)
            <div class="row">
                <div class="col-md-4">
                    <p class="text-muted mb-1">Paket</p>
                    <h5 class="mb-0">{{ $subscription->package?->name ?? '-' }}</h5>
                </div>
                <div class="col-md-3">
                    <p class="text-muted mb-1">Status</p>
                    <span class="{{ $subscription->status->badgeClass() }}">{{ $subscription->status->label() }}</span>
                    <span class="{{ $tenant->status->badgeClass() }}">{{ $tenant->status->label() }}</span>
                </div>
                <div class="col-md-3">
                    <p class="text-muted mb-1">Berlaku sampai</p>
                    <p class="mb-0">{{ $subscription->ends_at?->format('d M Y') ?? '-' }}</p>
                </div>
                <div class="col-md-2">
                    <p class="text-muted mb-1">Tagihan</p>
                    @if ($unpaidInvoice)
                        <a href="{{ route('subscription.invoices') }}">{{ $unpaidInvoice->number }}</a>
                    @else
                        <span class="text-muted">Tidak ada</span>
                    @endif
                </div>
            </div>
        @else
            <p class="text-muted mb-0">Klinik belum memiliki paket langganan. Pilih paket di bawah.</p>
        @endif
        <div class="mt-3">
            <x-button href="{{ route('subscription.invoices') }}" variant="secondary" icon="bx bx-receipt">Lihat tagihan paket</x-button>
        </div>
    </x-card>

    <div class="row">
        @forelse ($packages as $package)
            @php
                $isCurrent = $subscription && (int) $subscription->package_id === (int) $package->id;
            @endphp
            <div class="col-md-6 col-xl-4">
                <div class="card {{ $isCurrent ? 'border-primary' : '' }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="mb-0">{{ $package->name }}</h5>
                            @if ($isCurrent)
                                <span class="badge bg-primary">Paket saat ini</span>
                            @endif
                        </div>
                        <h3 class="mb-1">
                            @if ($package->isFree())
                                Gratis
                            @else
                                Rp {{ number_format((float) $package->price, 0, ',', '.') }}
                            @endif
                        </h3>
                        <p class="text-muted">{{ $package->interval->label() }} · trial {{ $package->trial_days }} hari</p>
                        <ul class="list-unstyled text-muted mb-3">
                            <li>Maks. {{ $package->max_branches }} cabang</li>
                            <li>Maks. {{ $package->max_users }} pengguna</li>
                        </ul>
                        @if ($canSubscribe)
                            <form method="POST" action="{{ route('subscription.subscribe', $package) }}">
                                @csrf
                                @if ($package->isFree())
                                    <x-button type="submit" variant="{{ $isCurrent ? 'secondary' : 'primary' }}">
                                        {{ $isCurrent ? 'Aktifkan ulang' : 'Pilih paket gratis' }}
                                    </x-button>
                                @elseif ($isCurrent && $unpaidInvoice)
                                    <x-button href="{{ route('subscription.invoices') }}" variant="warning">Bayar tagihan</x-button>
                                @elseif ($isCurrent)
                                    <x-button type="submit">Perpanjang paket</x-button>
                                @else
                                    <x-button type="submit">Pilih &amp; bayar paket</x-button>
                                @endif
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <x-card>
                    <p class="text-muted mb-0">Belum ada paket aktif. Hubungi pengelola platform.</p>
                </x-card>
            </div>
        @endforelse
    </div>
@endsection
