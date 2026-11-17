@extends('layouts.app')

@section('title', 'Add-ons Marketplace')

@section('content')
    <x-page-header title="Add-ons Marketplace" :breadcrumb="['Add-ons' => null, 'Marketplace' => route('addons.index')]" />
    <x-alert />

    <div class="row">
        @forelse ($addons as $addon)
            @php
                $subscription = $subscriptions->get($addon->id);
                $status = $subscription?->status;
            @endphp
            <div class="col-md-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="mb-0">{{ $addon->name }}</h5>
                            @if ($status)
                                <span class="{{ $status->badgeClass() }}">{{ $status->label() }}</span>
                            @else
                                <span class="badge bg-secondary">Belum aktif</span>
                            @endif
                        </div>
                        <p class="text-muted">{{ \Illuminate\Support\Str::limit($addon->description, 120) }}</p>
                        <p class="mb-3">
                            @if ($addon->isFree())
                                <strong>Gratis</strong>
                            @else
                                <strong>Rp {{ number_format((float) $addon->price, 0, ',', '.') }}</strong>
                                <span class="text-muted">/ {{ $addon->interval->label() }}</span>
                            @endif
                        </p>
                        <x-button href="{{ route('addons.show', $addon) }}" variant="primary">Detail</x-button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <x-card>
                    <p class="text-muted mb-0">Belum ada Add-on yang tersedia.</p>
                </x-card>
            </div>
        @endforelse
    </div>
@endsection
