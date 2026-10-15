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
            <tr>
                <td colspan="{{ isset($showTenant) ? 6 : 5 }}">
                    <details>
                        <summary class="small">Request dan response</summary>
                        <div class="row g-2 mt-2">
                            <div class="col-md-6">
                                <div class="text-muted small mb-1">Request</div>
                                <pre class="small mb-0" style="max-height:280px;overflow:auto">{{ json_encode($log->request_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small mb-1">Response</div>
                                <pre class="small mb-0" style="max-height:280px;overflow:auto">{{ json_encode($log->response_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                        </div>
                    </details>
                </td>
            </tr>
        @empty
            <tr><td colspan="{{ isset($showTenant) ? 6 : 5 }}" class="text-muted">Belum ada log.</td></tr>
        @endforelse
    </tbody>
</x-table>
