@extends('layouts.app')

@section('title', 'Katalog Add-ons')

@section('content')
    <x-page-header title="Katalog Add-ons" :breadcrumb="['Add-ons' => null, 'Katalog' => route('saas.addons.index')]" />
    <x-alert />

    @can('addon.manage')
        <x-card title="Add-on baru">
            <form method="POST" action="{{ route('saas.addons.store') }}">
                @csrf
                @include('saas.addons.partials.fields', ['formKey' => 'create', 'intervalOptions' => $intervalOptions])
                <div class="mt-3">
                    <x-button type="submit" icon="bx bx-plus">Simpan</x-button>
                </div>
            </form>
        </x-card>
    @endcan

    <x-card title="Daftar Add-on">
        <x-table>
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama</th>
                    <th>Harga</th>
                    <th>Interval</th>
                    <th>Tenant</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($addons as $addon)
                    <tr>
                        <td>{{ $addon->code }}</td>
                        <td>{{ $addon->name }}</td>
                        <td>Rp {{ number_format((float) $addon->price, 0, ',', '.') }}</td>
                        <td>{{ $addon->interval->label() }}</td>
                        <td>{{ $addon->subscriptions_count }}</td>
                        <td>
                            <span class="badge {{ $addon->is_active ? 'bg-success' : 'bg-secondary' }}">
                                {{ $addon->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-1 flex-wrap">
                                <x-button variant="soft-secondary" data-bs-toggle="modal" data-bs-target="#edit-addon-{{ $addon->id }}" icon="bx bx-edit">Ubah</x-button>
                                <form method="POST" action="{{ route('saas.addons.status', $addon) }}">
                                    @csrf
                                    <x-button type="submit" variant="{{ $addon->is_active ? 'soft-warning' : 'soft-success' }}">
                                        {{ $addon->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </x-button>
                                </form>
                            </div>
                        </td>
                    </tr>

                    <div class="modal fade" id="edit-addon-{{ $addon->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <form method="POST" action="{{ route('saas.addons.update', $addon) }}">
                                    @csrf
                                    @method('PUT')
                                    <div class="modal-header">
                                        <h5 class="modal-title">Ubah {{ $addon->name }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        @include('saas.addons.partials.fields', ['formKey' => 'edit', 'addon' => $addon, 'intervalOptions' => $intervalOptions])
                                    </div>
                                    <div class="modal-footer">
                                        <x-button type="button" variant="secondary" data-bs-dismiss="modal">Batal</x-button>
                                        <x-button type="submit" variant="primary">Simpan</x-button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <tr>
                        <td colspan="7" class="text-muted">Belum ada Add-on.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>
@endsection
