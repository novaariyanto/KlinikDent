<x-table>
    <thead>
        <tr>
            <th>Waktu</th>
            <th>User</th>
            <th>Event</th>
            <th>Modul</th>
            <th>Deskripsi</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($logs as $log)
            <tr>
                <td>{{ $log->created_at?->format('d M Y H:i') }}</td>
                <td>{{ $log->user?->name ?: '-' }}</td>
                <td><span class="{{ $log->eventBadgeClass() }}">{{ $log->eventLabel() }}</span></td>
                <td>{{ $log->moduleLabel() }}</td>
                <td>{{ $log->description }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-muted">Belum ada aktivitas.</td></tr>
        @endforelse
    </tbody>
</x-table>
