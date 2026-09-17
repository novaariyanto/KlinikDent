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
                            <th>Alamat</th>
                            <td>{{ $branch->address ?: '-' }}</td>
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
    </div>
@endsection
