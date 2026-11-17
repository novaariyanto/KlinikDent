@extends('layouts.app')

@section('title', 'Aktivasi Add-on Tenant')

@section('content')
    <x-page-header title="Aktivasi Add-on Tenant" :breadcrumb="['Add-ons' => route('saas.addons.index'), 'Aktivasi' => null]" />
    <x-alert />

    <x-card title="Filter">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Add-on</label>
                <select name="addon_id" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($addons as $addon)
                        <option value="{{ $addon->id }}" @selected((string) ($filters['addon_id'] ?? '') === (string) $addon->id)>{{ $addon->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tenant</label>
                <select name="tenant_id" class="form-select">
                    <option value="">Semua</option>
                    @foreach ($tenants as $tenant)
                        <option value="{{ $tenant->id }}" @selected((string) ($filters['tenant_id'] ?? '') === (string) $tenant->id)>{{ $tenant->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <x-button type="submit" variant="secondary">Terapkan</x-button>
            </div>
        </form>
    </x-card>

    <x-card title="Buat / pastikan aktivasi">
        <form method="POST" action="{{ route('saas.addon-subscriptions.ensure') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-4">
                <label class="form-label">Tenant</label>
                <select name="tenant_id" class="form-select" required>
                    <option value="">Pilih</option>
                    @foreach ($tenants as $tenant)
                        <option value="{{ $tenant->id }}">{{ $tenant->name }} ({{ $tenant->subdomain }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Add-on</label>
                <select name="addon_id" class="form-select" required>
                    <option value="">Pilih</option>
                    @foreach ($addons as $addon)
                        <option value="{{ $addon->id }}">{{ $addon->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="activate" value="1" id="activate-now" checked>
                    <label class="form-check-label" for="activate-now">Aktifkan sekarang</label>
                </div>
            </div>
            <div class="col-md-2">
                <x-button type="submit" variant="primary">Simpan</x-button>
            </div>
        </form>
    </x-card>

    <x-card title="Daftar aktivasi">
        <x-table>
            <thead>
                <tr>
                    <th>Tenant</th>
                    <th>Add-on</th>
                    <th>Status</th>
                    <th>Aktivasi</th>
                    <th>Berlaku sampai</th>
                    <th>Invoice</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subscriptions as $subscription)
                    <tr>
                        <td>{{ $subscription->tenant?->name }}</td>
                        <td>{{ $subscription->addon?->name }}</td>
                        <td><span class="{{ $subscription->status->badgeClass() }}">{{ $subscription->status->label() }}</span></td>
                        <td>{{ $subscription->activated_at?->format('d M Y H:i') ?? '-' }}</td>
                        <td>{{ $subscription->ends_at?->format('d M Y') ?? 'Tanpa batas' }}</td>
                        <td>
                            @forelse ($subscription->invoices as $invoice)
                                <div>{{ $invoice->number }} <span class="{{ $invoice->status->badgeClass() }}">{{ $invoice->status->label() }}</span></div>
                            @empty
                                <span class="text-muted">-</span>
                            @endforelse
                        </td>
                        <td class="text-end">
                            <div class="d-flex flex-column align-items-end gap-1">
                                <div class="d-flex gap-1 flex-wrap justify-content-end">
                                    <form method="POST" action="{{ route('saas.addon-subscriptions.activate', $subscription) }}">
                                        @csrf
                                        <x-button type="submit" variant="soft-success" size="sm">Aktifkan</x-button>
                                    </form>
                                    <form method="POST" action="{{ route('saas.addon-subscriptions.suspend', $subscription) }}">
                                        @csrf
                                        <x-button type="submit" variant="soft-warning" size="sm">Tangguhkan</x-button>
                                    </form>
                                    <form method="POST" action="{{ route('saas.addon-subscriptions.revoke', $subscription) }}">
                                        @csrf
                                        <x-button type="submit" variant="soft-danger" size="sm">Cabut</x-button>
                                    </form>
                                </div>
                                <form method="POST" action="{{ route('saas.addon-subscriptions.extend', $subscription) }}" class="d-flex gap-1">
                                    @csrf
                                    @method('PUT')
                                    <input type="date" name="ends_at" class="form-control form-control-sm" value="{{ $subscription->ends_at?->format('Y-m-d') }}">
                                    <x-button type="submit" variant="secondary" size="sm">Perpanjang</x-button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-muted">Belum ada data aktivasi.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
        {{ $subscriptions->links() }}
    </x-card>
@endsection
