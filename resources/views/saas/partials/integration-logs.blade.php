<x-table>
    <thead>
        <tr>
            <th>Waktu</th>
            @isset($showTenant)
                <th>Klinik</th>
            @endisset
            <th>Provider</th>
            <th>Aksi</th>
            <th>Status</th>
            <th>Error</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($logs as $log)
            <tr>
                <td>{{ $log->created_at?->format('d M Y H:i') }}</td>
                @isset($showTenant)
                    <td>{{ $log->tenant?->name ?: '-' }}</td>
                @endisset
                <td>{{ $log->provider->label() }}</td>
                <td>{{ $log->action }}</td>
                <td><span class="{{ $log->status->badgeClass() }}">{{ $log->status->label() }}</span></td>
                <td class="text-muted">{{ \Illuminate\Support\Str::limit($log->error_message, 80) ?: '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ isset($showTenant) ? 6 : 5 }}" class="text-muted">Belum ada log.</td></tr>
        @endforelse
    </tbody>
</x-table>
