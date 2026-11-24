@extends('layouts.app')

@section('title', $medicine->name)

@section('content')
    <x-page-header title="{{ $medicine->name }}" :breadcrumb="['Obat' => route('management.medicines.index'), $medicine->name => null]" />
    <x-alert />
    <div class="row">
        <div class="col-lg-8">
            <x-card title="Detail Obat">
                <x-table>
                    <tbody>
                        <tr><th width="180">Nama</th><td>{{ $medicine->name }}</td></tr>
                        <tr>
                            <th>Kode KFA</th>
                            <td>
                                @if (filled($medicine->kfa_code))
                                    {{ $medicine->kfa_code }}
                                @else
                                    <span class="text-muted">Belum diisi</span>
                                    <span class="badge bg-soft-warning text-warning font-size-11 ms-1">Wajib untuk kirim resep ke SATUSEHAT</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Kekuatan zat aktif</th>
                            <td>
                                @if ($medicine->strengthLabel())
                                    {{ $medicine->strengthLabel() }}
                                @else
                                    <span class="text-muted">Belum diisi</span>
                                    <span class="text-muted small d-block">Dipakai otomatis saat racikan d.t.d (SD).</span>
                                @endif
                            </td>
                        </tr>
                        <tr><th>Satuan kemasan</th><td>{{ $medicine->unitLabel() }}</td></tr>
                        <tr><th>Kategori</th><td>{{ $medicine->categoryLabel() }}</td></tr>
                        <tr><th>Harga Dasar</th><td>Rp {{ number_format((float) $medicine->base_price, 0, ',', '.') }}</td></tr>
                        <tr><th>Margin</th><td>{{ \App\Models\Medicine::MARGIN_TYPES[$medicine->margin_type] ?? $medicine->margin_type }} ({{ $medicine->marginLabel() }})</td></tr>
                        <tr><th>Harga Jual</th><td>Rp {{ number_format((float) $medicine->sell_price, 0, ',', '.') }}</td></tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <span class="{{ $medicine->is_active ? 'badge bg-success' : 'badge bg-danger' }}">
                                    {{ $medicine->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </x-table>
                <div class="mt-3 d-flex flex-wrap gap-2">
                    @can('update', $medicine)
                        <x-button href="{{ route('management.medicines.edit', $medicine) }}" icon="bx bx-edit">Edit</x-button>
                    @endcan
                    <x-button href="{{ route('management.medicines.index') }}" variant="secondary">Back</x-button>
                </div>
            </x-card>
        </div>
        <div class="col-lg-4">
            <x-card title="Stok tersedia">
                <p class="mb-1">Batch yang masih bisa dipakai</p>
                <h3 class="mb-0">{{ number_format($available ?? 0, 0, ',', '.') }}</h3>
                <p class="text-muted mb-0">{{ $medicine->unitLabel() }}</p>
            </x-card>
        </div>
    </div>

    <div class="row" id="stok">
        <div class="col-lg-8">
            <x-card title="Batch stok">
                <x-table>
                    <thead>
                        <tr>
                            <th>Cabang</th>
                            <th>Batch</th>
                            <th>Kedaluwarsa</th>
                            <th class="text-end">Qty</th>
                            <th>Status</th>
                            @can('create', App\Models\MedicineStock::class)
                                <th>Penyesuaian</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stocks as $stock)
                            <tr>
                                <td>{{ $stock->branch?->name ?: '—' }}</td>
                                <td>{{ $stock->batch_number }}</td>
                                <td>{{ $stock->expired_date?->format('d M Y') }}</td>
                                <td class="text-end">{{ number_format($stock->quantity, 0, ',', '.') }}</td>
                                <td>
                                    @if ($stock->quantity < 1)
                                        <span class="badge bg-secondary">Habis</span>
                                    @elseif ($stock->isExpired())
                                        <span class="badge bg-danger">Kedaluwarsa</span>
                                    @elseif ($stock->isExpiringSoon())
                                        <span class="badge bg-warning">Segera kedaluwarsa</span>
                                    @else
                                        <span class="badge bg-success">Tersedia</span>
                                    @endif
                                </td>
                                @can('update', $stock)
                                    <td style="min-width: 16rem;">
                                        <form method="POST" action="{{ route('management.medicines.stock.adjust', [$medicine, $stock]) }}" class="d-flex flex-wrap gap-1 align-items-start">
                                            @csrf
                                            <input type="number" name="delta" class="form-control form-control-sm" style="width: 5.5rem;" placeholder="+/-" required aria-label="Perubahan qty">
                                            <input type="text" name="notes" class="form-control form-control-sm" style="width: 8rem;" placeholder="Alasan" required maxlength="255" aria-label="Alasan penyesuaian">
                                            <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-muted">Belum ada batch stok untuk obat ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-table>
            </x-card>

            <x-card title="Mutasi terakhir">
                <x-table>
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Batch</th>
                            <th>Jenis</th>
                            <th class="text-end">Qty</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movements as $movement)
                            <tr>
                                <td>{{ $movement->created_at?->format('d M Y H:i') }}</td>
                                <td>{{ $movement->stock?->batch_number }}</td>
                                <td><span class="{{ $movement->type?->badgeClass() }}">{{ $movement->type?->label() }}</span></td>
                                <td class="text-end">{{ number_format($movement->quantity, 0, ',', '.') }}</td>
                                <td>{{ $movement->notes ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-muted">Belum ada mutasi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-table>
            </x-card>
        </div>
        @can('create', App\Models\MedicineStock::class)
            <div class="col-lg-4">
                <x-card title="Tambah stok">
                    <p class="text-muted">Batch yang sama di cabang yang sama akan ditambahkan ke stok yang sudah ada.</p>
                    <form method="POST" action="{{ route('management.medicines.stock.store', $medicine) }}">
                        @csrf
                        <x-select name="branch_id" label="Cabang" :options="$branchOptions" :selected="old('branch_id', auth()->user()?->branch_id)" placeholder="Pilih cabang" required />
                        <x-input name="batch_number" label="Nomor batch" :value="old('batch_number')" required />
                        <x-input name="expired_date" type="date" label="Tanggal kedaluwarsa" :value="old('expired_date')" required />
                        <x-input name="quantity" type="number" min="1" label="Jumlah masuk" :value="old('quantity')" required />
                        <x-input name="notes" label="Catatan" :value="old('notes')" />
                        <x-button type="submit" icon="bx bx-plus">Simpan stok</x-button>
                    </form>
                </x-card>
            </div>
        @endcan
    </div>
@endsection
