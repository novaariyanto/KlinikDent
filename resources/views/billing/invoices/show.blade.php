@extends('layouts.app')

@section('title', $invoice->number)

@section('content')
    <x-page-header :title="'Kasir '.$invoice->number" :breadcrumb="['Tagihan' => route('billing.invoices'), $invoice->number => null]" />
    <x-alert />

    @if (auth()->user()?->hasRole(\App\Enums\RoleName::Cashier) && ! $shift && $invoice->isOpen())
        <div class="alert alert-warning">Buka shift kasir sebelum menerima pembayaran. <a href="{{ route('cashier.shifts.open') }}">Buka shift</a></div>
    @endif

    @if (bccomp($overpaid, '0', 2) > 0)
        <div class="alert alert-warning">Kelebihan pembayaran Rp{{ number_format((float) $overpaid, 0, ',', '.') }}. Gunakan pengembalian dana. Riwayat pembayaran tidak dihapus.</div>
    @endif

    <x-card title="Cari pasien lain" class="mb-3">
        <label for="cashier-search" class="form-label">Nama, nomor rekam medis, telepon, atau nomor tagihan</label>
        <div class="d-flex gap-2">
            <input type="search" id="cashier-search" class="form-control" placeholder="Ketik minimal 2 huruf" autocomplete="off">
            <button type="button" class="btn btn-light text-nowrap" data-bs-toggle="modal" data-bs-target="#todayPatientsModal">Pasien hari ini</button>
        </div>
        <div id="cashier-search-status" class="text-muted small mt-2" hidden>Mencari...</div>
        <div id="cashier-search-results" class="list-group mt-2" hidden></div>
    </x-card>

    <div class="row">
        <div class="col-lg-8">
            <x-card title="Tagihan">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Pasien:</strong> {{ $invoice->patient?->name }}</p>
                        <p class="mb-1"><strong>No. RM:</strong> {{ $invoice->patient?->medical_record_number ?: '-' }}</p>
                        <p class="mb-1"><strong>Telepon:</strong> {{ $invoice->patient?->phone ?: '-' }}</p>
                        <p class="mb-1"><strong>Penjamin:</strong> {{ $invoice->payer?->name ?: '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Tagihan:</strong> {{ $invoice->number }}</p>
                        <p class="mb-1"><strong>Kunjungan:</strong> {{ $invoice->visit?->visit_date?->format('d M Y') ?: '-' }}</p>
                        <p class="mb-1"><strong>Dokter:</strong> {{ $invoice->visit?->doctor?->name ?: '-' }}</p>
                        <p class="mb-1"><strong>Cabang:</strong> {{ $invoice->branch?->name }}</p>
                        <p class="mb-0"><strong>Status:</strong> <span class="{{ $invoice->status->badgeClass() }}">{{ $invoice->status->label() }}</span></p>
                    </div>
                </div>
                <x-table>
                    <thead>
                        <tr>
                            <th>Jenis</th>
                            <th>Deskripsi</th>
                            <th>Qty</th>
                            <th>Harga</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoice->items as $item)
                            <tr>
                                <td>{{ $item->source_type->label() }}</td>
                                <td>{{ $item->description }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>{{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                                <td>{{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">Belum ada item.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">Subtotal</th>
                            <th>{{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</th>
                        </tr>
                        <tr>
                            <th colspan="4" class="text-end">Diskon</th>
                            <th>0</th>
                        </tr>
                        <tr>
                            <th colspan="4" class="text-end">Total</th>
                            <th>{{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</th>
                        </tr>
                        <tr>
                            <th colspan="4" class="text-end">Dibayar</th>
                            <th>{{ number_format((float) $invoice->paid_amount, 0, ',', '.') }}</th>
                        </tr>
                        <tr>
                            <th colspan="4" class="text-end">Sisa</th>
                            <th>{{ number_format((float) $invoice->remainingAmount(), 0, ',', '.') }}</th>
                        </tr>
                    </tfoot>
                </x-table>
                <p class="text-muted small mb-0">Diskon invoice, voucher, dan deposit pasien tidak dipakai. Obat masuk tagihan begitu tercatat. Penyerahan dikonfirmasi terpisah, boleh sebelum atau sesudah pembayaran. Stok dipotong saat farmasi memenuhi resep.</p>
            </x-card>

            @php
                $pendingHandover = collect($review['medicines'])->contains(
                    fn (array $row) => ! $row['confirmed'] && $row['billed'] > 0 && $row['match'] !== 'Tidak berlaku'
                );
            @endphp
            <div class="mb-3">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#confirmServicesModal">Konfirmasi tindakan dan obat</button>
                <span class="text-muted ms-2">
                    {{ $review['summary']['procedure_confirmed'] }}/{{ $review['summary']['procedure_count'] }} tindakan
                    · {{ $review['summary']['medicine_confirmed'] }}/{{ $review['summary']['medicine_count'] }} obat
                    @if ($review['summary']['mismatch_count'] > 0)
                        · {{ $review['summary']['mismatch_count'] }} perlu diperiksa
                    @endif
                    @if ($pendingHandover)
                        · obat belum diserahkan
                    @endif
                </span>
            </div>

            <x-card title="Riwayat pembayaran">
                @forelse ($invoice->payments as $payment)
                    <p class="mb-1">
                        {{ $payment->paid_at?->format('d M Y H:i') }} —
                        {{ $payment->isRefund() ? '−' : '' }}{{ number_format((float) $payment->amount, 0, ',', '.') }}
                        ({{ $payment->kind?->label() ?? 'Pembayaran' }} · {{ $payment->method->label() }})
                        — {{ $payment->cashier?->name }}
                        @if ($payment->notes) <span class="text-muted">{{ $payment->notes }}</span> @endif
                    </p>
                @empty
                    <p class="text-muted mb-0">Belum ada pembayaran.</p>
                @endforelse
            </x-card>
        </div>

        <div class="col-lg-4">
            <div class="cashier-pay">
                <x-card title="Dokumen">
                    <x-button href="{{ route('billing.invoices.print', $invoice) }}" variant="light" icon="bx bx-printer" target="_blank">Cetak</x-button>
                    <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#printModal">Ukuran cetak</button>
                </x-card>

                @php
                    $showPay = $invoice->isOpen() && auth()->user()?->can('create', App\Models\Payment::class);
                    $showRefund = ! $invoice->isVoid() && (float) $invoice->paid_amount > 0 && auth()->user()?->can('create', App\Models\Payment::class);
                    $openRefund = $showRefund && ($errors->has('reason') || filled(old('reason')));
                @endphp

                @if ($showPay && $showRefund)
                    <ul class="nav nav-tabs nav-tabs-custom mb-3">
                        <li class="nav-item">
                            <button class="nav-link {{ $openRefund ? '' : 'active' }}" type="button" data-bs-toggle="tab" data-bs-target="#invoice-pay">Pembayaran</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link {{ $openRefund ? 'active' : '' }}" type="button" data-bs-toggle="tab" data-bs-target="#invoice-refund">Pengembalian</button>
                        </li>
                    </ul>
                @endif

                <div @class(['tab-content' => $showPay && $showRefund])>
                    @if ($showPay)
                        <div @class(['tab-pane fade' => $showPay && $showRefund, 'show active' => $showPay && $showRefund && ! $openRefund]) id="invoice-pay">
                            <x-card title="Terima pembayaran">
                                <dl class="row mb-3">
                                    <dt class="col-6">Total</dt><dd class="col-6 text-end">{{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</dd>
                                    <dt class="col-6">Diskon</dt><dd class="col-6 text-end">0</dd>
                                    <dt class="col-6">Sudah dibayar</dt><dd class="col-6 text-end">{{ number_format((float) $invoice->paid_amount, 0, ',', '.') }}</dd>
                                    <dt class="col-6">Sisa</dt><dd class="col-6 text-end"><strong>{{ number_format((float) $invoice->remainingAmount(), 0, ',', '.') }}</strong></dd>
                                </dl>
                                <form method="POST" action="{{ route('billing.invoices.pay', $invoice) }}" id="pay-form">
                                    @csrf
                                    <input type="hidden" name="cashier" value="1">
                                    @php
                                        $rupiahInput = function ($value) {
                                            if ($value === null || $value === '') {
                                                return '';
                                            }
                                            $text = trim((string) $value);
                                            if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $text)) {
                                                return preg_replace('/,\d+$/', '', $text);
                                            }

                                            return number_format((float) $text, 0, ',', '.');
                                        };
                                    @endphp
                                    <div class="mb-3">
                                        <label for="pay-amount" class="form-label">Nominal pembayaran <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="text" name="amount" id="pay-amount" inputmode="numeric" autocomplete="off" required class="form-control @error('amount') is-invalid @enderror" value="{{ $rupiahInput(old('amount', $invoice->remainingAmount())) }}">
                                        </div>
                                        @error('amount')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <x-select name="method" id="pay-method" label="Metode" :options="$methods" :selected="old('method', 'cash')" required />
                                    <x-input name="reference" label="No. referensi" :value="old('reference')" maxlength="64" />
                                    <div id="cash-box">
                                        <div class="mb-3">
                                            <label for="pay-tendered" class="form-label">Uang diterima</label>
                                            <div class="input-group">
                                                <span class="input-group-text">Rp</span>
                                                <input type="text" name="tendered" id="pay-tendered" inputmode="numeric" autocomplete="off" class="form-control @error('tendered') is-invalid @enderror" value="{{ $rupiahInput(old('tendered')) }}">
                                            </div>
                                            @error('tendered')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <p class="mb-3">Uang kembalian: <strong id="pay-change">Rp 0</strong></p>
                                    </div>
                                    <x-input name="notes" label="Catatan" :value="old('notes')" />
                                    <x-button type="submit" variant="success" icon="bx bx-money" id="pay-button">Proses pembayaran</x-button>
                                </form>
                            </x-card>
                        </div>
                    @endif

                    @if ($showRefund)
                        <div @class(['tab-pane fade' => $showPay && $showRefund, 'show active' => $openRefund]) id="invoice-refund">
                            <x-card title="Pengembalian dana">
                                <form method="POST" action="{{ route('billing.invoices.refund', $invoice) }}">
                                    @csrf
                                    <x-input name="amount" id="refund_amount" type="number" label="Nominal" :value="old('amount')" required />
                                    <x-select name="method" id="refund_method" label="Metode" :options="$methods" :selected="old('method', 'cash')" required />
                                    <x-input name="reason" label="Alasan" :value="old('reason')" required />
                                    <x-button type="submit" variant="warning">Kembalikan</x-button>
                                </form>
                            </x-card>
                        </div>
                    @endif
                </div>

                @can('void', $invoice)
                    <x-card title="Batalkan tagihan">
                        <form method="POST" action="{{ route('billing.invoices.void', $invoice) }}" onsubmit="return confirm('Batalkan tagihan ini?')">
                            @csrf
                            <x-input name="reason" id="void-reason" label="Alasan" required />
                            <x-button type="submit" variant="danger">Void</x-button>
                        </form>
                    </x-card>
                @endcan

                @if ($invoice->isVoid())
                    <x-card title="Void">
                        <p class="mb-0">{{ $invoice->void_reason }}</p>
                        <p class="text-muted mb-0">{{ $invoice->voided_at?->format('d M Y H:i') }}</p>
                    </x-card>
                @endif
            </div>
        </div>
    </div>

    <x-modal id="declineModal" title="Obat tidak jadi diambil" size="modal-lg">
        <form method="POST" id="decline-form">
            @csrf
            <div id="decline-paid" class="alert alert-warning" hidden>Tagihan sudah lunas. Permintaan hanya dicatat. Total tidak berubah. Uang dikembalikan lewat panel pengembalian dana.</div>
            <p class="mb-1"><strong id="decline-name"></strong></p>
            <p class="mb-1">Diresepkan: <span id="decline-prescribed"></span></p>
            <p class="mb-1">Ditagih: <span id="decline-billed"></span></p>
            <p class="mb-1">Sudah dikonfirmasi diserahkan: <span id="decline-handed"></span></p>
            <p class="mb-3">Petugas: {{ auth()->user()?->name }}</p>
            <x-input name="quantity" id="decline-qty" type="number" label="Jumlah tidak jadi diambil" min="1" step="1" required />
            <div class="mb-3">
                <label for="decline-reason" class="form-label">Alasan</label>
                <select name="reason" id="decline-reason" class="form-select" required>
                    <option value="">Pilih alasan</option>
                    @foreach ($declineReasons as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label for="decline-detail" class="form-label">Catatan</label>
                <textarea name="detail" id="decline-detail" class="form-control" rows="2" maxlength="500"></textarea>
                <div class="form-text" id="decline-clinical" hidden>Penggantian obat atau perubahan resep klinis harus diputuskan dokter atau farmasi. Form ini hanya mengeluarkan nilai obat dari tagihan.</div>
            </div>
            <p>Nilai yang keluar dari sisa tagihan: <strong id="decline-amount">0</strong></p>
            <p class="small text-muted">Resep tidak dihapus. Obat yang sudah dikonfirmasi diserahkan tidak memakai tombol ini.</p>
            <x-button type="submit" variant="danger" id="decline-submit">Simpan pembatalan</x-button>
        </form>
    </x-modal>

    <x-modal id="confirmServicesModal" title="Konfirmasi tindakan dan obat" size="modal-xl">
        <p class="small text-muted">Konfirmasi hanya mencatat penyerahan dan tidak mengubah total. Obat boleh diserahkan sebelum bayar maupun setelah lunas. Sebelum diserahkan, obat yang tidak diambil memakai tombol Tidak jadi diambil. Setelah lunas, selisih uang memakai pengembalian dana.</p>
        @if ($review['confirmed_by'])
            <p class="small text-muted">Konfirmasi terakhir: {{ $review['confirmed_by'] }} · {{ $review['confirmed_at'] }}</p>
        @endif
        @if ($review['mismatches'] !== [])
            <div class="alert alert-warning">
                <ul class="mb-2">
                    @foreach ($review['mismatches'] as $mismatch)
                        <li><span class="badge bg-warning text-dark">{{ $mismatch['level'] }}</span> {{ $mismatch['text'] }}</li>
                    @endforeach
                </ul>
                <p class="mb-0">Kasir tidak mengubah rekam medis dari halaman ini. Koreksi tindakan lewat modul perawatan, koreksi resep lewat dokter atau farmasi.</p>
            </div>
        @else
            <div class="alert alert-success">Tidak ada selisih jumlah atau harga antara layanan dan tagihan.</div>
        @endif

        <h6>Tindakan</h6>
        @if ($review['procedures'] === [])
            <p class="text-muted">Tidak ada tindakan pada kunjungan ini.</p>
        @else
            <div class="table-responsive mb-3">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Tindakan</th>
                            <th>Petugas</th>
                            <th>Tanggal</th>
                            <th>Qty</th>
                            <th>Harga</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($review['procedures'] as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['performer'] }}</td>
                                <td>{{ $row['date'] }}</td>
                                <td>{{ $row['quantity'] }}</td>
                                <td>{{ number_format((float) $row['price'], 0, ',', '.') }}</td>
                                <td>{{ number_format((float) $row['total'], 0, ',', '.') }}</td>
                                <td>
                                    <span class="badge bg-light text-dark">{{ $row['billing'] }}</span>
                                    <span class="badge {{ $row['match'] === 'Sesuai' ? 'bg-success' : ($row['match'] === 'Tidak sesuai' ? 'bg-danger' : ($row['match'] === 'Tidak berlaku' ? 'bg-dark' : 'bg-warning text-dark')) }}">{{ $row['match'] }}</span>
                                    <div class="small text-muted">{{ $row['note'] }}</div>
                                </td>
                                <td class="text-nowrap">
                                    @if ($row['confirmed'])
                                        <span class="badge bg-success">Dikonfirmasi</span>
                                    @else
                                        @can('update', $invoice)
                                            <form method="POST" action="{{ route('billing.invoices.confirm', $invoice) }}" onsubmit="this.querySelector('button').disabled = true">
                                                @csrf
                                                <input type="hidden" name="procedures[]" value="{{ $row['id'] }}">
                                                <button type="submit" class="btn btn-sm btn-primary">Konfirmasi</button>
                                            </form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <h6>Obat</h6>
        @if ($review['medicines'] === [])
            <p class="text-muted">Tidak ada obat pada kunjungan ini.</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Obat</th>
                            <th>Jumlah</th>
                            <th>Aturan</th>
                            <th>Nilai</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($review['medicines'] as $row)
                            <tr>
                                <td>
                                    {{ $row['name'] }}
                                    @if ($row['form'])<div class="small text-muted">{{ $row['form'] }}</div>@endif
                                </td>
                                <td>
                                    Resep {{ $row['prescribed'] }}<br>
                                    Tagih {{ $row['billed'] }}<br>
                                    Tidak diambil {{ $row['declined'] }}
                                </td>
                                <td>{{ $row['sig'] }}</td>
                                <td>
                                    {{ number_format((float) $row['unit_price'], 0, ',', '.') }}
                                    <div class="small">{{ number_format((float) $row['total'], 0, ',', '.') }}</div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark">Resep {{ $row['rx_status'] }}</span>
                                    <span class="badge bg-light text-dark">{{ $row['handover'] }}</span>
                                    <span class="badge {{ $row['match'] === 'Sesuai' ? 'bg-success' : ($row['match'] === 'Tidak sesuai' ? 'bg-danger' : ($row['match'] === 'Tidak berlaku' ? 'bg-dark' : 'bg-warning text-dark')) }}">{{ $row['match'] }}</span>
                                    <div class="small text-muted">{{ $row['note'] }}</div>
                                </td>
                                <td class="text-nowrap">
                                    @if ($row['confirmed'])
                                        <span class="badge bg-success">Dikonfirmasi</span>
                                    @else
                                        @can('update', $invoice)
                                            <form method="POST" action="{{ route('billing.invoices.confirm', $invoice) }}" class="d-inline" onsubmit="this.querySelector('button').disabled = true">
                                                @csrf
                                                <input type="hidden" name="medicines[]" value="{{ $row['id'] }}">
                                                <button type="submit" class="btn btn-sm btn-primary">Konfirmasi diserahkan</button>
                                            </form>
                                        @endcan
                                    @endif
                                    @if ($row['can_decline'])
                                        @can('update', $invoice)
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-danger decline-medicine"
                                                data-url="{{ route('billing.invoices.medicines.decline', [$invoice, $row['id']]) }}"
                                                data-name="{{ $row['name'] }}"
                                                data-prescribed="{{ $row['prescribed'] }}"
                                                data-billed="{{ $row['billed'] }}"
                                                data-handed="{{ $row['handed'] }}"
                                                data-max="{{ $row['decline_max'] }}"
                                                data-price="{{ $row['unit_price'] }}"
                                                data-paid="{{ $row['paid_lock'] ? '1' : '0' }}"
                                                data-block="{{ $row['block'] }}"
                                            >Tidak jadi diambil</button>
                                        @endcan
                                    @elseif ($row['block'])
                                        <span class="small text-muted d-block">{{ $row['block'] }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-modal>

    <x-modal id="todayPatientsModal" title="Pasien hari ini" size="modal-lg">
        <input type="search" id="today-patients-filter" class="form-control mb-3" placeholder="Saring nama atau nomor rekam medis" autocomplete="off">
        <div id="today-patients-status" class="text-muted">Memuat daftar...</div>
        <div id="today-patients-list" class="list-group"></div>
    </x-modal>

    <x-modal id="printModal" title="Cetak bukti" size="modal-lg">
        <p>Pilih ukuran. Struk termal membuka halaman cetak browser. A5 dan A4 membuka PDF.</p>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-primary" target="_blank" href="{{ route('billing.invoices.receipt', [$invoice, 'paper' => '58']) }}">Termal 58 mm</a>
            <a class="btn btn-primary" target="_blank" href="{{ route('billing.invoices.receipt', [$invoice, 'paper' => '80']) }}">Termal 80 mm</a>
            <a class="btn btn-light" target="_blank" href="{{ route('billing.invoices.print', [$invoice, 'paper' => 'a5']) }}">A5</a>
            <a class="btn btn-light" target="_blank" href="{{ route('billing.invoices.print', [$invoice, 'paper' => 'a4']) }}">A4</a>
        </div>
        <p class="small text-muted mt-3 mb-0">Angka pada struk mengikuti total tagihan terakhir. Baris obat yang tidak diambil tetap tercantum tanpa menambah total.</p>
        @if ($pendingHandover)
            <p class="small mb-0">Masih ada obat yang belum dikonfirmasi diserahkan. Catat penyerahannya lewat Konfirmasi tindakan dan obat, termasuk bila obat diberikan setelah pembayaran.</p>
        @endif
    </x-modal>
@endsection

@push('styles')
    <style>
        @media (min-width: 992px) {
            .cashier-pay { position: sticky; top: 88px; }
        }
    </style>
@endpush

@push('scripts')
    <script>
        (function () {
            const money = (value) => new Intl.NumberFormat('id-ID').format(Math.round(value || 0));
            const esc = (value) => String(value ?? '-').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
            const currentInvoiceId = @json($invoice->id);
            const generateUrl = @json(route('billing.invoices.generate'));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const search = document.getElementById('cashier-search');
            const results = document.getElementById('cashier-search-results');
            const status = document.getElementById('cashier-search-status');
            let timer = null;

            search?.addEventListener('input', () => {
                clearTimeout(timer);
                const term = search.value.trim();
                if (term.length < 2) {
                    results.hidden = true;
                    results.innerHTML = '';
                    status.hidden = true;
                    return;
                }
                status.hidden = false;
                timer = setTimeout(async () => {
                    try {
                        const response = await fetch(@json(route('billing.invoices.search')) + '?q=' + encodeURIComponent(term), {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const payload = await response.json();
                        const rows = payload.data || [];
                        results.innerHTML = rows.length
                            ? rows.map((row) => '<a class="list-group-item list-group-item-action" href="' + esc(row.url) + '"><strong>' + esc(row.patient) + '</strong> · RM ' + esc(row.mrn) + '<br><span class="small text-muted">' + esc(row.number) + ' · ' + esc(row.visit) + ' · ' + esc(row.doctor) + ' · ' + esc(row.status) + '</span></a>').join('')
                            : '<div class="list-group-item text-muted">Tidak ada tagihan yang cocok.</div>';
                        results.hidden = false;
                    } catch (error) {
                        results.innerHTML = '<div class="list-group-item text-danger">Pencarian gagal. Coba lagi.</div>';
                        results.hidden = false;
                    } finally {
                        status.hidden = true;
                    }
                }, 300);
            });

            const todayModal = document.getElementById('todayPatientsModal');
            const todayList = document.getElementById('today-patients-list');
            const todayStatus = document.getElementById('today-patients-status');
            const todayFilter = document.getElementById('today-patients-filter');
            let todayRows = [];

            const todayRowHtml = (row) => {
                const meta = [row.mrn ? 'RM ' + esc(row.mrn) : '', esc(row.doctor), esc(row.visit_status), row.number ? esc(row.number) + ' · ' + esc(row.status) : 'Belum ada tagihan'].filter(Boolean).join(' · ');
                const current = Number(row.id) === Number(currentInvoiceId) ? ' <span class="badge bg-light text-dark">Tagihan ini</span>' : '';
                const body = '<strong>' + esc(row.patient) + '</strong>' + current + '<br><span class="small text-muted">' + meta + '</span>';
                if (row.url) {
                    return '<a class="list-group-item list-group-item-action today-patient" href="' + esc(row.url) + '" data-search="' + esc((row.patient || '') + ' ' + (row.mrn || '')) + '">' + body + '</a>';
                }
                if (row.visit_id) {
                    return '<form method="POST" action="' + esc(generateUrl) + '" class="list-group-item today-patient d-flex justify-content-between align-items-center gap-2" data-search="' + esc((row.patient || '') + ' ' + (row.mrn || '')) + '"><input type="hidden" name="_token" value="' + esc(csrf) + '"><input type="hidden" name="visit_id" value="' + esc(row.visit_id) + '"><div>' + body + '</div><button type="submit" class="btn btn-sm btn-primary">Pilih</button></form>';
                }
                return '<div class="list-group-item today-patient" data-search="' + esc((row.patient || '') + ' ' + (row.mrn || '')) + '">' + body + '</div>';
            };

            const paintToday = () => {
                if (!todayList) return;
                const term = (todayFilter?.value || '').trim().toLowerCase();
                const rows = todayRows.filter((row) => !term || ((row.patient || '') + ' ' + (row.mrn || '')).toLowerCase().includes(term));
                todayList.innerHTML = !todayRows.length
                    ? ''
                    : (rows.length ? rows.map(todayRowHtml).join('') : '<div class="list-group-item text-muted">Tidak ada pasien yang cocok.</div>');
            };

            todayModal?.addEventListener('show.bs.modal', async () => {
                if (todayStatus) {
                    todayStatus.hidden = false;
                    todayStatus.textContent = 'Memuat daftar...';
                }
                if (todayList) todayList.innerHTML = '';
                try {
                    const response = await fetch(@json(route('billing.invoices.today-patients')), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const payload = await response.json();
                    todayRows = payload.data || [];
                    if (todayStatus) {
                        todayStatus.hidden = todayRows.length > 0;
                        todayStatus.textContent = todayRows.length ? '' : 'Tidak ada pasien hari ini di cabang ini.';
                    }
                    paintToday();
                } catch (error) {
                    if (todayStatus) {
                        todayStatus.hidden = false;
                        todayStatus.textContent = 'Daftar pasien gagal dimuat. Tutup lalu buka lagi.';
                    }
                }
            });
            todayFilter?.addEventListener('input', paintToday);

            const method = document.getElementById('pay-method');
            const amount = document.getElementById('pay-amount');
            const tendered = document.getElementById('pay-tendered');
            const change = document.getElementById('pay-change');
            const cashBox = document.getElementById('cash-box');

            const rupiahDigits = (value) => {
                const text = String(value ?? '').trim().replace(/\s/g, '').replace(/^rp/i, '');
                if (!text) return '';
                if (text.includes(',')) return text.split(',')[0].replace(/\D/g, '');
                if (/^\d+\.\d{1,2}$/.test(text)) return text.split('.')[0];
                return text.replace(/\D/g, '');
            };
            const formatRupiah = (value) => {
                const digits = rupiahDigits(value);
                return digits ? new Intl.NumberFormat('id-ID').format(Number(digits)) : '';
            };
            const bindRupiah = (input) => {
                if (!input) return;
                const paint = () => {
                    const formatted = formatRupiah(input.value);
                    if (input.value !== formatted) input.value = formatted;
                };
                input.addEventListener('input', paint);
                paint();
            };
            bindRupiah(amount);
            bindRupiah(tendered);

            const syncCash = () => {
                if (!method || !cashBox) return;
                const cash = method.value === 'cash';
                cashBox.hidden = !cash;
                if (tendered) tendered.required = cash;
                const received = cash ? Number(rupiahDigits(tendered?.value) || 0) : 0;
                const due = Number(rupiahDigits(amount?.value) || 0);
                if (change) change.textContent = 'Rp ' + money(Math.max(0, received - due));
            };

            method?.addEventListener('change', syncCash);
            amount?.addEventListener('input', syncCash);
            tendered?.addEventListener('input', syncCash);
            syncCash();

            document.getElementById('pay-form')?.addEventListener('submit', () => {
                const button = document.getElementById('pay-button');
                if (!button) return;
                button.disabled = true;
                button.textContent = 'Memproses...';
            });

            const declineForm = document.getElementById('decline-form');
            const declineQty = document.getElementById('decline-qty');
            const declineAmount = document.getElementById('decline-amount');
            let declinePrice = 0;

            const syncDecline = () => {
                const qty = Number(declineQty?.value || 0);
                if (declineAmount) declineAmount.textContent = money(qty * declinePrice);
            };

            document.querySelectorAll('.decline-medicine').forEach((button) => {
                button.addEventListener('click', () => {
                    declinePrice = Number(button.dataset.price || 0);
                    declineForm.action = button.dataset.url;
                    if (window.bootstrap) {
                        const confirmModal = document.getElementById('confirmServicesModal');
                        const declineModal = document.getElementById('declineModal');
                        if (confirmModal) window.bootstrap.Modal.getOrCreateInstance(confirmModal).hide();
                        if (declineModal) window.bootstrap.Modal.getOrCreateInstance(declineModal).show();
                    }
                    document.getElementById('decline-name').textContent = button.dataset.name || '';
                    document.getElementById('decline-prescribed').textContent = button.dataset.prescribed || '0';
                    document.getElementById('decline-billed').textContent = button.dataset.billed || '0';
                    document.getElementById('decline-handed').textContent = button.dataset.handed || '0';
                    declineQty.max = button.dataset.max || '1';
                    declineQty.value = button.dataset.max || '1';
                    document.getElementById('decline-paid').hidden = button.dataset.paid !== '1';
                    document.getElementById('decline-clinical').hidden = true;
                    syncDecline();
                });
            });

            declineQty?.addEventListener('input', syncDecline);
            document.getElementById('decline-reason')?.addEventListener('change', (event) => {
                const other = event.target.value === 'lainnya';
                const clinical = event.target.value === 'diganti' || event.target.value === 'dikurangi';
                document.getElementById('decline-detail').required = other;
                document.getElementById('decline-clinical').hidden = !clinical;
            });
            document.getElementById('decline-form')?.addEventListener('submit', () => {
                const button = document.getElementById('decline-submit');
                if (button) button.disabled = true;
            });

            const showModal = (id) => {
                const open = () => {
                    const modal = document.getElementById(id);
                    if (modal && window.bootstrap) {
                        window.bootstrap.Modal.getOrCreateInstance(modal).show();
                    }
                };
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', open);
                } else {
                    open();
                }
            };

            @if (session('cashier_print'))
                showModal('printModal');
            @elseif (! $invoice->isVoid() && ! $errors->any() && ($invoice->isOpen() || $pendingHandover))
                showModal('confirmServicesModal');
            @endif
        })();
    </script>
@endpush
