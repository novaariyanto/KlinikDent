@php
    $finishPrescriptions = $visit->prescriptions;
    $finishRxItems = $finishPrescriptions->flatMap(fn ($rx) => $rx->items);
    $finishDraftRx = $finishPrescriptions->filter(fn ($rx) => $rx->isDraft());
    $finishSsIssues = collect($finishDraftRx)->flatMap(
        fn ($rx) => \App\Support\Clinical\PrescriptionSatuSehatReadiness::forPrescription($rx)
    )->all();
    $finishSsBlocking = \App\Support\Clinical\PrescriptionSatuSehatReadiness::blockingMessages($finishSsIssues);
    $finishSsWarnings = \App\Support\Clinical\PrescriptionSatuSehatReadiness::warningMessages($finishSsIssues);
@endphp
<div id="care-finish-rx" class="care-finish-review mb-3" data-draft-count="{{ $finishDraftRx->count() }}" data-item-count="{{ $finishRxItems->count() }}" data-ss-block="{{ $finishSsBlocking !== [] ? '1' : '0' }}">
    <h6 class="mb-2">Obat / resep ({{ $finishRxItems->count() }})</h6>
    @if ($finishRxItems->isEmpty())
        <p class="text-muted small mb-0">Tidak ada obat dicatat.</p>
    @else
        <x-table>
            <thead>
                <tr>
                    <th>Obat</th>
                    <th>Dosis</th>
                    <th>Frekuensi</th>
                    <th>Aturan pakai</th>
                    <th>Qty</th>
                    <th>Status resep</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($finishPrescriptions as $prescription)
                    @foreach ($prescription->items as $item)
                        <tr>
                            <td>
                                {{ $item->label() }}
                                @if ($item->isCompound())
                                    @foreach ($item->ingredients as $ingredient)
                                        <div class="font-size-12">{{ $ingredient->compositionLabel() }}</div>
                                    @endforeach
                                @endif
                            </td>
                            <td>{{ $item->dosage ?: '-' }}</td>
                            <td>{{ $item->frequency ?: '-' }}</td>
                            <td>
                                {{ $item->duration ?: '-' }}
                                @if ($item->course)
                                    <div class="font-size-12">Durasi {{ $item->course }}</div>
                                @endif
                            </td>
                            <td>{{ $item->quantity }}</td>
                            <td><span class="{{ $prescription->status->badgeClass() }}">{{ $prescription->status->label() }}</span></td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </x-table>
    @endif
    @if ($finishDraftRx->isNotEmpty())
        <div class="alert alert-info mt-2 mb-0 py-2">
            {{ $finishDraftRx->count() }} resep draft akan <strong>dikirim ke farmasi</strong> bersama penyelesaian kunjungan. Stok belum dipotong.
        </div>
        @if ($finishSsBlocking !== [])
            <div class="alert alert-danger mt-2 mb-0 py-2">
                <div class="fw-semibold mb-1">Belum siap kirim (farmasi / SATUSEHAT)</div>
                <ul class="mb-0 ps-3">
                    @foreach ($finishSsBlocking as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @elseif ($finishSsWarnings !== [])
            <div class="alert alert-warning mt-2 mb-0 py-2">
                <div class="fw-semibold mb-1">Bisa dikirim ke farmasi; sebagian tidak ke SATUSEHAT</div>
                <ul class="mb-0 ps-3">
                    @foreach ($finishSsWarnings as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</div>
