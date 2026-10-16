@extends('layouts.app')

@section('title', $branch->name)

@section('content')
    <x-page-header title="{{ $branch->name }}" :breadcrumb="['Cabang' => route('branches.index'), $branch->name => null]" />

    <x-alert />

    <div class="row">
        <div class="col-lg-8">
            <x-card title="Detail Cabang">
                <x-table>
                    <tbody>
                        @if (auth()->user()?->isPlatformAdmin())
                            <tr>
                                <th width="180">Klinik</th>
                                <td>{{ $branch->tenant?->name ?: '-' }}</td>
                            </tr>
                        @endif
                        <tr>
                            <th width="180">Nama</th>
                            <td>{{ $branch->name }}</td>
                        </tr>
                        <tr>
                            <th>Telepon</th>
                            <td>{{ $branch->phone ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td>{{ $branch->email ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th>Website</th>
                            <td>{{ $branch->website ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th>Alamat</th>
                            <td>{{ $branch->address ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th>Kota</th>
                            <td>{{ $branch->city ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th>Kode Pos</th>
                            <td>{{ $branch->postal_code ?: '-' }}</td>
                        </tr>
                        <tr>
                            <th>Koordinat</th>
                            <td>
                                @if ($branch->latitude !== null && $branch->longitude !== null)
                                    {{ $branch->latitude }}, {{ $branch->longitude }}@if ($branch->altitude !== null), {{ $branch->altitude }} m @endif
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <span class="{{ $branch->is_active ? 'badge bg-success' : 'badge bg-danger' }}">
                                    {{ $branch->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Jam Operasional</th>
                            <td>
                                @forelse (\App\Models\Branch::WEEK_DAYS as $day => $label)
                                    <div>{{ $label }}: {{ $branch->opening_hours[$day] ?? '-' }}</div>
                                @empty
                                    -
                                @endforelse
                            </td>
                        </tr>
                    </tbody>
                </x-table>

                <div class="mt-3 d-flex flex-wrap gap-2">
                    @can('update', $branch)
                        <x-button href="{{ route('branches.edit', $branch) }}" icon="bx bx-edit">Edit</x-button>
                    @endcan
                    <x-button href="{{ route('branches.index') }}" variant="secondary">Back</x-button>
                </div>
            </x-card>
        </div>

        <div class="col-lg-4">
            <x-card title="SATUSEHAT">
                @php
                    $hasCoords = $branch->latitude !== null && $branch->longitude !== null;
                @endphp

                <p class="mb-1 small text-muted">Status</p>
                <p class="mb-3">
                    @if (! $satusehatEnabled)
                        <span class="badge bg-secondary">Modul nonaktif</span>
                    @elseif (! $satusehatSetting?->connection_ok)
                        <span class="badge bg-warning">Belum diuji koneksi</span>
                    @elseif (filled($satusehatSetting?->location_id))
                        <span class="badge bg-success">Lokasi terhubung</span>
                    @else
                        <span class="badge bg-warning">Lokasi belum disinkronkan</span>
                    @endif
                </p>

                <p class="mb-1 small text-muted">Location ID</p>
                <p class="mb-3">
                    @if (filled($satusehatSetting?->location_id))
                        <code>{{ $satusehatSetting->location_id }}</code>
                        @if (filled($satusehatSetting->location_name))
                            <span class="d-block text-muted small mt-1">{{ $satusehatSetting->location_name }}</span>
                        @endif
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </p>

                @if (! $hasCoords)
                    <div class="alert alert-warning py-2 px-3 small">
                        Isi lintang &amp; bujur di form edit cabang sebelum sync lokasi.
                    </div>
                @endif

                <div class="d-grid gap-2">
                    @can('update', $branch)
                        @if ($canSyncSatusehat && $hasCoords)
                            <form method="POST" action="{{ route('branches.satusehat', $branch) }}">
                                @csrf
                                <x-button type="submit" class="w-100" variant="info" icon="bx bx-cloud-upload">
                                    {{ filled($satusehatSetting?->location_id) ? 'Perbarui lokasi SATUSEHAT' : 'Sync lokasi ke SATUSEHAT' }}
                                </x-button>
                            </form>
                        @elseif (auth()->user()?->can('integration.manage'))
                            <x-button
                                href="{{ route('integrations.satusehat', ['branch_id' => $branch->id]) }}"
                                variant="secondary"
                                class="w-100"
                                icon="bx bx-cog"
                            >Buka pengaturan SATUSEHAT</x-button>
                        @endif
                    @endcan

                    @can('satusehat.view')
                        <x-button
                            href="{{ route('integrations.satusehat', ['branch_id' => $branch->id]) }}"
                            variant="light"
                            class="w-100"
                            icon="bx bx-cloud"
                        >Modul SATUSEHAT</x-button>
                    @endcan
                </div>

                <p class="text-muted small mt-3 mb-0">
                    Sync mengirim nama, alamat, dan koordinat cabang sebagai Location induk (Site) di bawah Organization SATUSEHAT.
                </p>
            </x-card>
        </div>
    </div>
@endsection
