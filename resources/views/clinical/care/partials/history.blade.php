<x-card title="Riwayat Kunjungan Lain">
    <x-table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Dokter</th>
                <th>Diagnosis</th>
                <th>Tindakan</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($history as $item)
                <tr>
                    <td>{{ $item->visit_date?->format('d M Y') }}</td>
                    <td>{{ $item->doctor?->name ?: '-' }}</td>
                    <td>{{ $item->diagnoses->pluck('description')->join(', ') ?: '-' }}</td>
                    <td>{{ $item->procedureRecords->pluck('procedure.name')->filter()->join(', ') ?: '-' }}</td>
                    <td class="text-end">
                        @can('view', $item)
                            <a href="{{ route('care.show', $item) }}">Buka</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted">Belum ada kunjungan lain.</td></tr>
            @endforelse
        </tbody>
    </x-table>
</x-card>
