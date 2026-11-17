@extends('layouts.app')

@section('title', $addon->name)

@section('content')
    <x-page-header :title="$addon->name" :breadcrumb="['Add-ons' => route('addons.index'), $addon->name => null]" />
    <x-alert />

    <div class="row">
        <div class="col-lg-8">
            <x-card title="Informasi Add-on">
                <p>{{ $addon->description }}</p>

                @if (is_array($addon->benefits) && count($addon->benefits))
                    <h6 class="mt-3">Manfaat</h6>
                    <ul>
                        @foreach ($addon->benefits as $benefit)
                            <li>{{ $benefit }}</li>
                        @endforeach
                    </ul>
                @endif

                @if ($addon->terms)
                    <h6 class="mt-3">Ketentuan penggunaan</h6>
                    <p class="text-muted mb-0">{{ $addon->terms }}</p>
                @endif
            </x-card>
        </div>
        <div class="col-lg-4">
            <x-card title="Status & aktivasi">
                <p class="mb-1">Harga</p>
                <p class="mb-3">
                    @if ($addon->isFree())
                        <strong>Gratis</strong>
                    @else
                        <strong>Rp {{ number_format((float) $addon->price, 0, ',', '.') }}</strong>
                        <span class="text-muted">/ {{ $addon->interval->label() }}</span>
                    @endif
                    @if ((int) $addon->trial_days > 0)
                        <br><span class="text-muted">Trial {{ $addon->trial_days }} hari</span>
                    @endif
                </p>

                <p class="mb-1">Status</p>
                <p class="mb-3">
                    @if ($subscription)
                        <span class="{{ $subscription->status->badgeClass() }}">{{ $subscription->status->label() }}</span>
                        @if ($subscription->ends_at)
                            <br><span class="text-muted small">Berlaku sampai {{ $subscription->ends_at->format('d M Y') }}</span>
                        @endif
                    @else
                        <span class="badge bg-secondary">Belum aktif</span>
                    @endif
                </p>

                @if ($unpaidInvoice)
                    <div class="alert alert-warning py-2">
                        Tagihan belum lunas: {{ $unpaidInvoice->number }}
                    </div>
                @endif

                @if ($isOwner)
                    @if (! $subscription || $subscription->status->value !== 'active')
                        @if ($addon->isFree() || (int) $addon->trial_days > 0)
                            <form method="POST" action="{{ route('addons.activate', $addon) }}" class="mb-2">
                                @csrf
                                <x-button type="submit" variant="primary" icon="bx bx-check">Aktifkan Add-on</x-button>
                            </form>
                        @endif

                        @if (! $addon->isFree())
                            @if ($midtransReady)
                                <form method="POST" action="{{ route('addons.pay', $addon) }}">
                                    @csrf
                                    <x-button type="submit" variant="primary" icon="bx bx-credit-card">Bayar & Aktifkan</x-button>
                                </form>
                            @else
                                <p class="text-muted small mb-0">Payment gateway belum dikonfigurasi. Hubungi Superadmin untuk aktivasi berbayar.</p>
                            @endif
                        @endif
                    @else
                        <p class="text-success mb-0">Add-on sudah aktif untuk klinik ini.</p>
                    @endif
                @else
                    <p class="text-muted mb-0">Hanya Owner yang dapat mengaktifkan Add-on.</p>
                @endif
            </x-card>
        </div>
    </div>
@endsection
