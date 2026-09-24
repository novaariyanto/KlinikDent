<td>
    @if (! ($line['tracked'] ?? false))
        <span class="text-muted">Tidak dipotong</span>
    @else
        {{ $line['available'] ?? 0 }}
        @if (! ($line['enough'] ?? false))
            <div class="text-danger font-size-13">Stok tidak cukup</div>
        @endif
    @endif
</td>
<td>
    @if (! ($line['tracked'] ?? false))
        <span class="text-muted">-</span>
    @else
        @forelse ($line['allocations'] ?? [] as $allocation)
            <div>{{ $allocation['stock']->batch_number }} ({{ $allocation['quantity'] }}) — exp {{ $allocation['stock']->expired_date?->format('d M Y') }}</div>
        @empty
            <span class="text-muted">Tidak ada batch usable</span>
        @endforelse
    @endif
</td>
