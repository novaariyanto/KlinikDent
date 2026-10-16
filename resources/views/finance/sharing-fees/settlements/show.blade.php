@extends('layouts.app')

@section('title', 'Detail Settlement #'.$settlement->id)

@section('content')
    <x-page-header
        :title="'Settlement #'.$settlement->id"
        :breadcrumb="['Keuangan' => route('finance.index'), 'Settlement' => route('finance.sharing-fees.settlements.index'), '#'.$settlement->id => null]"
    />
    <x-alert />

    <div class="row">
        <div class="col-lg-4">
            <x-card title="Ringkasan">
                <dl class="row mb-0">
                    <dt class="col-5">Dokter</dt>
                    <dd class="col-7">{{ $settlement->doctor?->name }}</dd>
                    <dt class="col-5">Cabang</dt>
                    <dd class="col-7">{{ $settlement->branch?->name ?? '—' }}</dd>
                    <dt class="col-5">Periode</dt>
                    <dd class="col-7">
                        {{ $settlement->period_type?->label() }}<br>
                        <small>{{ $settlement->period_start?->format('d M Y') }} – {{ $settlement->period_end?->format('d M Y') }}</small>
                    </dd>
                    <dt class="col-5">Status</dt>
                    <dd class="col-7"><span class="{{ $settlement->status?->badgeClass() }}">{{ $settlement->status?->label() }}</span></dd>
                    <dt class="col-5">Gross fee</dt>
                    <dd class="col-7 text-end">{{ number_format((float) $settlement->gross_fee_amount, 0, ',', '.') }}</dd>
                    <dt class="col-5">Adjustment</dt>
                    <dd class="col-7 text-end">{{ number_format((float) $settlement->adjustment_amount, 0, ',', '.') }}</dd>
                    <dt class="col-5">Net fee</dt>
                    <dd class="col-7 text-end fw-semibold">{{ number_format((float) $settlement->net_fee_amount, 0, ',', '.') }}</dd>
                    <dt class="col-5">Dibayar</dt>
                    <dd class="col-7 text-end">{{ number_format((float) $settlement->paid_amount, 0, ',', '.') }}</dd>
                    <dt class="col-5">Sisa</dt>
                    <dd class="col-7 text-end">{{ number_format((float) $settlement->outstandingAmount(), 0, ',', '.') }}</dd>
                </dl>

                @if ($settlement->isDraft())
                    @can('approve', $settlement)
                        <form method="POST" action="{{ route('finance.sharing-fees.settlements.approve', $settlement) }}" class="mt-3" onsubmit="return confirm('Setujui settlement ini? Rincian tidak dapat diedit setelah disetujui.')">
                            @csrf
                            <x-button type="submit" icon="bx bx-check">Setujui</x-button>
                        </form>
                    @endcan
                @endif
            </x-card>

            @can('pay', $settlement)
                @if (! $settlement->isDraft() && bccomp($settlement->outstandingAmount(), '0', 2) > 0)
                    <x-card title="Catat pembayaran">
                        <form method="POST" action="{{ route('finance.sharing-fees.settlements.pay', $settlement) }}" class="row g-2">
                            @csrf
                            <div class="col-12">
                                <x-input name="amount" label="Nominal" type="number" step="0.01" :value="old('amount', $settlement->outstandingAmount())" required />
                            </div>
                            <div class="col-12">
                                <x-select name="method" label="Metode" :options="$methods" :selected="old('method', 'transfer')" required />
                            </div>
                            <div class="col-12">
                                <x-select
                                    name="cash_account_id"
                                    label="Akun kas"
                                    :options="$accounts->mapWithKeys(fn ($a) => [$a->id => $a->name.' ('.number_format((float) $a->balance, 0, ',', '.').')'])->all()"
                                    :selected="old('cash_account_id')"
                                    required
                                />
                            </div>
                            <div class="col-12">
                                <x-input name="paid_on" label="Tanggal bayar" type="date" :value="old('paid_on', now()->toDateString())" required />
                            </div>
                            <div class="col-12">
                                <x-input name="reference" label="Referensi" :value="old('reference')" />
                            </div>
                            <div class="col-12">
                                <x-input name="notes" label="Catatan" :value="old('notes')" />
                            </div>
                            <div class="col-12">
                                <x-button type="submit" icon="bx bx-money">Simpan pembayaran</x-button>
                            </div>
                        </form>
                    </x-card>
                @endif
            @endcan

            @can('adjust', $settlement)
                @if (! $settlement->isDraft())
                    <x-card title="Adjustment">
                        <form method="POST" action="{{ route('finance.sharing-fees.settlements.adjust', $settlement) }}" class="row g-2">
                            @csrf
                            <div class="col-12">
                                <x-input name="amount" label="Nominal (+/-)" type="number" step="0.01" :value="old('amount')" required />
                                <p class="form-text">Gunakan nilai negatif untuk pengurangan (mis. refund).</p>
                            </div>
                            <div class="col-12">
                                <x-input name="reason" label="Alasan" :value="old('reason')" required />
                            </div>
                            <div class="col-12">
                                <x-button type="submit" icon="bx bx-edit">Tambah adjustment</x-button>
                            </div>
                        </form>
                    </x-card>
                @endif
            @endcan
        </div>

        <div class="col-lg-8">
            <x-card title="Rincian item fee">
                <x-table>
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Tindakan</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Basis</th>
                            <th class="text-end">Potongan</th>
                            <th class="text-end">Tarif</th>
                            <th class="text-end">Fee</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($settlement->items as $item)
                            <tr>
                                <td>{{ $item->invoice_number }}</td>
                                <td>{{ $item->item_description }}</td>
                                <td class="text-end">{{ $item->quantity }}</td>
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
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted">Tidak ada item.</td></tr>
                        @endforelse
                    </tbody>
                </x-table>
            </x-card>

            <x-card title="Riwayat pembayaran">
                <x-table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Metode</th>
                            <th>Referensi</th>
                            <th>Oleh</th>
                            <th class="text-end">Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($settlement->payments as $payment)
                            <tr>
                                <td>{{ $payment->paid_on?->format('d M Y') }}</td>
                                <td>{{ $payment->method?->label() }}</td>
                                <td>{{ $payment->reference ?: '—' }}</td>
                                <td>{{ $payment->creator?->name }}</td>
                                <td class="text-end">{{ number_format((float) $payment->amount, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">Belum ada pembayaran.</td></tr>
                        @endforelse
                    </tbody>
                </x-table>
            </x-card>

            <x-card title="Adjustment">
                <x-table>
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Alasan</th>
                            <th>Oleh</th>
                            <th class="text-end">Nominal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($settlement->adjustments as $adjustment)
                            <tr>
                                <td>{{ $adjustment->created_at?->format('d M Y H:i') }}</td>
                                <td>{{ $adjustment->reason }}</td>
                                <td>{{ $adjustment->creator?->name }}</td>
                                <td class="text-end">{{ number_format((float) $adjustment->amount, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">Belum ada adjustment.</td></tr>
                        @endforelse
                    </tbody>
                </x-table>
            </x-card>
        </div>
    </div>
@endsection
