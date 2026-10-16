@extends('layouts.app')

@section('title', 'Laporan Sharing Fee per Dokter')

@section('content')
    <x-page-header title="Laporan Sharing Fee per Dokter" :breadcrumb="['Keuangan' => route('finance.index'), 'Sharing Fee' => null, 'Laporan' => null]" />
    <x-alert />

    <x-card title="Filter">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label" for="date_from">Dari</label>
                <input id="date_from" type="date" name="date_from" value="{{ $from }}" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="date_to">Sampai</label>
                <input id="date_to" type="date" name="date_to" value="{{ $to }}" class="form-control" required>
            </div>
            <div class="col-md-3">
                <x-select
                    name="branch_id"
                    label="Cabang"
                    :options="$branches->mapWithKeys(fn ($b) => [$b->id => $b->name])->all()"
                    :selected="$branchId"
                    placeholder="Semua cabang"
                />
            </div>
            <div class="col-md-3">
                <x-select
                    name="doctor_user_id"
                    label="Dokter"
                    :options="$doctors->mapWithKeys(fn ($d) => [$d->id => $d->name])->all()"
                    :selected="$doctorUserId"
                    placeholder="Semua dokter"
                />
            </div>
            <div class="col-md-2 d-flex gap-2">
                <x-button type="submit" icon="bx bx-search">Terapkan</x-button>
                <a
                    href="{{ route('finance.sharing-fees.reports.doctors', array_merge(request()->query(), ['export' => 'csv'])) }}"
                    class="btn btn-outline-success"
                >CSV</a>
            </div>
        </form>
    </x-card>

    <x-card title="Rekap per dokter">
        <x-table>
            <thead>
                <tr>
                    <th>Dokter</th>
                    <th class="text-end">Draft</th>
                    <th class="text-end">Approved</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Net</th>
                    <th class="text-end">Outstanding</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($summary as $row)
                    <tr>
                        <td>{{ $row->doctor_name }}</td>
                        <td class="text-end">{{ number_format((float) $row->draft_total, 0, ',', '.') }}</td>
                        <td class="text-end">{{ number_format((float) $row->approved_total, 0, ',', '.') }}</td>
                        <td class="text-end">{{ number_format((float) $row->paid_total, 0, ',', '.') }}</td>
                        <td class="text-end">{{ number_format((float) $row->net_total, 0, ',', '.') }}</td>
                        <td class="text-end">{{ number_format((float) $row->outstanding_total, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">Tidak ada data pada filter ini.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    <x-card title="Rincian item">
        <x-table>
            <thead>
                <tr>
                    <th>Dokter</th>
                    <th>Invoice</th>
                    <th>Tindakan</th>
                    <th class="text-end">Basis</th>
                    <th class="text-end">Potongan</th>
                    <th class="text-end">Tarif</th>
                    <th class="text-end">Fee</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($details as $item)
                    <tr>
                        <td>{{ $item->settlement?->doctor?->name }}</td>
                        <td>{{ $item->invoice_number }}</td>
                        <td>{{ $item->item_description }}</td>
                        <td class="text-end">{{ number_format((float) $item->fee_base, 0, ',', '.') }}</td>
                        <td class="text-end">{{ number_format((float) $item->material_deduction, 0, ',', '.') }}</td>
                        <td class="text-end">
                            @if ($item->calc_type === App\Enums\SharingFeeCalcType::Percentage)
                                {{ number_format((float) $item->rate, 2, ',', '.') }}%
                            @else
                                {{ number_format((float) $item->rate, 0, ',', '.') }}
                            @endif
                        </td>
                        <td class="text-end">{{ number_format((float) $item->fee_amount, 0, ',', '.') }}</td>
                        <td><span class="{{ $item->settlement?->status?->badgeClass() }}">{{ $item->settlement?->status?->label() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">Tidak ada rincian.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        @if ($details->hasPages())
            <div class="mt-3">{{ $details->links() }}</div>
        @endif
    </x-card>
@endsection
