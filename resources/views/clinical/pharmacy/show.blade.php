@extends('layouts.app')

@php
    $fromPelayanan = request('from') === 'pelayanan';
    $rxBreadcrumb = $fromPelayanan
        ? ['Pelayanan' => route('examinations.initial'), 'Resep #'.$prescription->id => null]
        : ['Resep Masuk' => route('pharmacy.prescriptions.incoming'), 'Detail' => null];
    $invoice = $prescription->visit?->currentInvoice();
    $canOpenExam = $fromPelayanan || auth()->user()?->can('examination.view');
    $canOpenSoap = $prescription->visit && App\Support\Clinical\CareTabs::canAccessCare(auth()->user());
    $canOpenCashier = $invoice && auth()->user()?->can('viewAny', App\Models\Invoice::class);
    $canFulfill = $prescription->isSent()
        && auth()->user()?->can('prescription.update')
        && (auth()->user()?->can('pharmacy.manage') || auth()->user()?->can('stock.manage'));
    $backUrl = $fromPelayanan
        ? route('examinations.initial')
        : route($prescription->isSent() ? 'pharmacy.prescriptions.incoming' : 'pharmacy.prescriptions.completed');
@endphp

@section('title', 'Detail Resep')

@section('content')
    <x-page-header title="Resep #{{ $prescription->id }}" :breadcrumb="$rxBreadcrumb" />
    <x-alert />
    @include('clinical.pharmacy.partials.nav', ['current' => $prescription->isSent() ? 'pharmacy.prescriptions.incoming' : 'pharmacy.prescriptions.completed'])
    <x-card title="Resep">
        <p class="mb-1"><strong>Pasien:</strong> {{ $prescription->visit?->patient?->name }} <span class="text-muted">{{ $prescription->visit?->patient?->medical_record_number }}</span></p>
        <p class="mb-1"><strong>Cabang:</strong> {{ $prescription->visit?->branch?->name ?: '-' }}</p>
        <p class="mb-1"><strong>Dokter:</strong> {{ $prescription->doctor?->name ?: '-' }}</p>
        <p class="mb-3">
            <strong>Status:</strong>
            <span class="{{ $prescription->status->badgeClass() }}">{{ $prescription->status->label() }}</span>
        </p>
        <x-table>
            <thead>
                <tr>
                    <th>Obat</th>
                    <th>Dosis</th>
                    <th>Frekuensi</th>
                    <th>Aturan pakai</th>
                    <th>Qty</th>
                    @if ($prescription->isSent())
                        <th>Stok cabang</th>
                        <th>Batch FEFO</th>
                    @endif
                    <th class="text-end">Label</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($prescription->items as $item)
                    @php
                        $lines = $availability[$item->id] ?? [];
                        $ready = $item->isCompound() ? null : ($lines[0] ?? null);
                    @endphp
                    @php
                        $itemSs = \App\Support\Clinical\PrescriptionSatuSehatReadiness::forItem($item);
                        $itemSsBlock = \App\Support\Clinical\PrescriptionSatuSehatReadiness::blockingMessages($itemSs);
                        $itemSsWarn = \App\Support\Clinical\PrescriptionSatuSehatReadiness::warningMessages($itemSs);
                    @endphp
                    <tr class="{{ $ready && ($ready['tracked'] ?? false) && ! $ready['enough'] ? 'table-danger' : '' }}">
                        <td>
                            <x-medicine-meta :medicine="$item->medicine" :name="$item->label()" :show-missing-kfa="! $item->isCompound()" />
                            @if ($item->medication_type)
                                <div class="text-muted font-size-12">{{ $item->medication_type->value }} · {{ $item->medication_type->label() }}</div>
                            @endif
                            @if ($item->isCompound())
                                <span class="badge bg-light text-dark">Racikan</span>
                            @endif
                            @if ($itemSsBlock !== [])
                                <div class="text-danger font-size-12">Belum siap SATUSEHAT</div>
                            @elseif ($itemSsWarn !== [])
                                <div class="text-warning font-size-12">Farmasi OK · tanpa SATUSEHAT</div>
                            @else
                                <div class="text-success font-size-12">Siap farmasi &amp; SATUSEHAT</div>
                            @endif
                        </td>
                        <td>{{ $item->dosage ?: '-' }}</td>
                        <td>{{ $item->frequency ?: '-' }}</td>
                        <td>
                            {{ $item->duration ?: '-' }}
                            @if ($item->course)
                                <div class="text-muted font-size-12">{{ $item->course }}</div>
                            @endif
                        </td>
                        <td>{{ $item->quantity }}@if (! $item->isCompound() && filled($ready['unit'] ?? null)) {{ $ready['unit'] }}@endif</td>
                        @if ($prescription->isSent())
                            @if ($item->isCompound())
                                <td colspan="2" class="text-muted">Stok dihitung per bahan</td>
                            @else
                                @include('clinical.pharmacy.partials.stock-cells', ['line' => $ready])
                            @endif
                        @endif
                        <td class="text-end">
                            <a href="{{ route('pharmacy.prescriptions.item-label', [$prescription, $item]) }}"
                               target="_blank" rel="noopener"
                               class="btn btn-sm btn-light border"
                               title="Cetak label"
                               aria-label="Cetak label {{ $item->label() }}">
                                <i class="bx bx-printer"></i>
                            </a>
                        </td>
                    </tr>
                    @if ($item->isCompound())
                        @foreach ($item->ingredients as $index => $ingredient)
                            @php($line = $lines[$index] ?? null)
                            <tr class="{{ $line && ($line['tracked'] ?? false) && ! $line['enough'] ? 'table-danger' : '' }}">
                                <td class="ps-4">
                                    <x-medicine-meta :medicine="$ingredient->medicine" :name="$ingredient->name" show-missing-kfa />
                                    @if ($ingredient->strength_value && $ingredient->strength_unit)
                                        <div class="text-muted font-size-12">d.t.d {{ rtrim(rtrim(number_format((float) $ingredient->strength_value, 4, '.', ''), '0'), '.') }} {{ $ingredient->strength_unit }}</div>
                                    @endif
                                </td>
                                <td colspan="3" class="text-muted">{{ $ingredient->unit ?: 'Bahan racikan' }}</td>
                                <td>{{ $ingredient->quantity }}</td>
                                @if ($prescription->isSent())
                                    @include('clinical.pharmacy.partials.stock-cells', ['line' => $line])
                                @endif
                                <td></td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach
            </tbody>
        </x-table>
        <div class="mt-3 d-flex flex-wrap gap-2">
            @if ($canOpenExam)
                <x-button href="{{ route('examinations.initial') }}" variant="secondary" icon="bx bx-list-ul">Pemeriksaan</x-button>
            @endif
            @if ($canOpenSoap)
                <x-button href="{{ route('care.show', ['visit' => $prescription->visit, 'tab' => 'soap', 'from' => 'pelayanan']) }}" variant="secondary" icon="bx bx-plus-medical">SOAP</x-button>
            @endif
            @if ($canOpenCashier)
                <x-button href="{{ route('billing.invoices.show', ['invoice' => $invoice, 'from' => 'pelayanan']) }}" variant="secondary" icon="bx bx-receipt">Kasir</x-button>
            @endif
            @if ($prescription->items->isNotEmpty())
                <x-button
                    href="{{ route('pharmacy.prescriptions.labels', $prescription) }}"
                    variant="secondary"
                    icon="bx bx-printer"
                    target="_blank"
                    rel="noopener"
                >Cetak semua label</x-button>
            @endif
            <x-button href="{{ $backUrl }}" variant="secondary">Kembali</x-button>
            @if ($canFulfill)
                @if ($stockShort)
                    <x-button type="button" variant="success" disabled>Stok tidak cukup</x-button>
                @else
                    <form action="{{ route('pharmacy.prescriptions.fulfill', $prescription) }}" method="POST" onsubmit="return confirm('Serahkan resep dan potong stok FEFO?')">
                        @csrf
                        <x-button type="submit" variant="success" icon="bx bx-check">Proses &amp; Serahkan</x-button>
                    </form>
                @endif
            @endif
        </div>
    </x-card>
@endsection
