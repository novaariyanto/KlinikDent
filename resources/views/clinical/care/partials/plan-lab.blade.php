<section class="care-section" id="lab">
    <div class="care-section__head">
        <h5 class="care-section__title">Lab gigi</h5>
        <p class="care-section__hint">Pesanan kerja laboratorium untuk kunjungan ini.</p>
    </div>
    <div class="care-section__body">
        <x-table>
            <thead>
                <tr>
                    <th>Jenis</th>
                    <th>Gigi</th>
                    <th>Warna</th>
                    <th>Laboratorium</th>
                    <th>Jatuh tempo</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($labOrders ?? [] as $order)
                    <tr>
                        <td>{{ $order->work_type }}</td>
                        <td>{{ $order->tooth_numbers ?: '-' }}</td>
                        <td>{{ $order->shade ?: '-' }}</td>
                        <td>{{ $order->vendor ?: '-' }}</td>
                        <td>{{ $order->due_date?->format('d M Y') ?: '-' }}</td>
                        <td>{{ $order->status->label() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">Belum ada pesanan lab.</td></tr>
                @endforelse
            </tbody>
        </x-table>

        @can('procedure.manage')
            @if ($writable)
                <form method="POST" action="{{ route('care.lab.store', $visit) }}" class="mt-3">
                    @csrf
                    <div class="row">
                        <div class="col-md-6"><x-input name="work_type" label="Jenis kerja" placeholder="Crown porselen" required /></div>
                        <div class="col-md-6"><x-input name="tooth_numbers" label="Gigi" /></div>
                        <div class="col-md-4"><x-input name="shade" label="Warna" /></div>
                        <div class="col-md-4"><x-input name="vendor" label="Laboratorium" /></div>
                        <div class="col-md-4"><x-input name="due_date" type="date" label="Jatuh tempo" /></div>
                    </div>
                    <x-button type="submit" icon="bx bx-plus">Catat pesanan</x-button>
                </form>
            @endif
        @endcan
    </div>
</section>
