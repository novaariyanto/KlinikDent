@extends('layouts.app')

@section('title', 'Langganan')

@section('content')
    <x-page-header title="Langganan Klinik" :breadcrumb="['Subscription' => null, 'Langganan' => route('saas.subscriptions.index')]" />
    <x-alert />

    @can('create', App\Models\SaasSubscription::class)
        <x-card title="Mulai langganan">
            <form method="POST" action="{{ route('saas.subscriptions.store') }}" class="row g-2">
                @csrf
                <div class="col-md-4">
                    <x-select name="tenant_id" label="Klinik" :options="$tenants->mapWithKeys(fn ($tenant) => [$tenant->id => $tenant->name])->all()" :selected="old('tenant_id')" required />
                </div>
                <div class="col-md-4">
                    <x-select name="package_id" label="Paket" :options="$packages->mapWithKeys(fn ($package) => [$package->id => $package->name])->all()" :selected="old('package_id')" required />
                </div>
                <div class="col-md-2 d-flex align-items-center">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="trial" value="1" id="trial" @checked(old('trial'))>
                        <label class="form-check-label" for="trial">Trial</label>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <x-button type="submit" icon="bx bx-plus">Simpan</x-button>
                </div>
            </form>
        </x-card>
    @endcan

    <x-card title="Daftar langganan">
        <x-table>
            <thead>
                <tr>
                    <th>Klinik</th>
                    <th>Paket</th>
                    <th>Status</th>
                    <th>Mulai</th>
                    <th>Berakhir</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subscriptions as $subscription)
                    <tr>
                        <td>{{ $subscription->tenant?->name }}</td>
                        <td>{{ $subscription->package?->name }}</td>
                        <td><span class="{{ $subscription->status->badgeClass() }}">{{ $subscription->status->label() }}</span></td>
                        <td>{{ $subscription->starts_at?->format('d M Y') }}</td>
                        <td>{{ $subscription->ends_at?->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">Belum ada langganan.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        {{ $subscriptions->links() }}
    </x-card>
@endsection
