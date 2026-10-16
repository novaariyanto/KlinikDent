@extends('layouts.app')

@section('title', 'Aturan Sharing Fee')

@section('content')
    <x-page-header title="Aturan Sharing Fee" :breadcrumb="['Keuangan' => route('finance.index'), 'Sharing Fee' => null, 'Aturan' => null]" />
    <x-alert />

    <x-card title="Daftar aturan">
        <x-slot:header-actions>
            @can('create', App\Models\SharingFeeRule::class)
                <x-button href="{{ route('finance.sharing-fees.rules.create') }}" icon="bx bx-plus">Tambah aturan</x-button>
            @endcan
        </x-slot:header-actions>

        <p class="text-muted small mb-3">
            Aturan umum (Semua Dokter · Semua Cabang · Semua Tindakan) berlaku sebagai default.
            Aturan khusus dengan cakupan lebih sempit otomatis mengalahkan aturan umum pada transaksi yang cocok.
        </p>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <x-select
                    name="doctor_user_id"
                    label="Dokter"
                    :options="[$scopeAll => 'Semua dokter (aturan umum)'] + $doctors->mapWithKeys(fn ($d) => [$d->id => $d->name])->all()"
                    :selected="request('doctor_user_id')"
                    placeholder="Semua filter"
                />
            </div>
            <div class="col-md-3">
                <x-select
                    name="is_active"
                    label="Status aktif"
                    :options="['1' => 'Aktif', '0' => 'Nonaktif']"
                    :selected="request('is_active')"
                    placeholder="Semua"
                />
            </div>
            <div class="col-md-3">
                <x-button type="submit" icon="bx bx-search">Filter</x-button>
            </div>
        </form>

        <x-table>
            <thead>
                <tr>
                    <th>Cakupan</th>
                    <th>Jenis</th>
                    <th class="text-end">Tarif</th>
                    <th class="text-end">Prioritas</th>
                    <th>Berlaku</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rules as $rule)
                    @php $status = $rule->scheduleStatus(); @endphp
                    <tr class="{{ $rule->isGeneral() ? 'table-light' : '' }}">
                        <td>
                            <div class="fw-medium">{{ $rule->coverageSummary() }}</div>
                            @if ($rule->isGeneral())
                                <span class="badge bg-primary-subtle text-primary">Aturan umum</span>
                            @else
                                <span class="badge bg-warning-subtle text-warning">Aturan khusus</span>
                            @endif
                        </td>
                        <td>{{ $rule->calc_type?->label() }}</td>
                        <td class="text-end">
                            @if ($rule->calc_type === App\Enums\SharingFeeCalcType::Percentage)
                                {{ number_format((float) $rule->rate, 2, ',', '.') }}%
                            @else
                                {{ number_format((float) $rule->rate, 0, ',', '.') }}
                            @endif
                        </td>
                        <td class="text-end">{{ $rule->priority }}</td>
                        <td>
                            {{ $rule->effective_from?->format('d M Y') }}
                            @if ($rule->effective_to)
                                – {{ $rule->effective_to->format('d M Y') }}
                            @else
                                – ∞
                            @endif
                        </td>
                        <td><span class="{{ $status['class'] }}">{{ $status['label'] }}</span></td>
                        <td class="text-end text-nowrap">
                            @can('update', $rule)
                                <a href="{{ route('finance.sharing-fees.rules.edit', $rule) }}" class="btn btn-sm btn-soft-primary">Edit</a>
                            @endcan
                            @can('delete', $rule)
                                @if ($rule->is_active)
                                    <form method="POST" action="{{ route('finance.sharing-fees.rules.destroy', $rule) }}" class="d-inline" onsubmit="return confirm('Nonaktifkan aturan ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-soft-danger">Nonaktifkan</button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted">Belum ada aturan sharing fee. Buat aturan umum 40% sebagai default klinik.</td></tr>
                @endforelse
            </tbody>
        </x-table>

        @if ($rules->hasPages())
            <div class="mt-3">{{ $rules->links() }}</div>
        @endif
    </x-card>
@endsection
