<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <div class="fw-semibold">{{ $detail['headline'] }}</div>
        <div class="text-muted small">{{ $detail['when'] }}</div>
    </div>
    <span class="{{ $detail['status']->badgeClass() }}">{{ $detail['status']->label() }}</span>
</div>

<p class="mb-3">{{ $detail['summary'] }}</p>

@if ($detail['error'])
    <div class="alert alert-danger">{{ $detail['error'] }}</div>
@endif

@if ($detail['encounter_id'] || $detail['visit_id'])
    <dl class="row mb-3 small">
        @if ($detail['visit_id'])
            <dt class="col-sm-5 text-muted fw-normal">Nomor kunjungan di klinik</dt>
            <dd class="col-sm-7">{{ $detail['visit_id'] }}</dd>
        @endif
        @if ($detail['encounter_id'])
            <dt class="col-sm-5 text-muted fw-normal">Nomor kunjungan di SATUSEHAT</dt>
            <dd class="col-sm-7"><code>{{ $detail['encounter_id'] }}</code></dd>
        @endif
    </dl>
@endif

@if ($detail['failures'] !== [])
    <div class="mb-3">
        <div class="fw-semibold mb-2">Kegagalan resource</div>
        <div class="border rounded">
            @foreach ($detail['failures'] as $failure)
                <div class="@if (! $loop->last) border-bottom @endif px-3 py-2">
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div>
                            <span class="fw-semibold">{{ $failure['type'] }}</span>
                            <code class="ms-1 small">{{ $failure['local_key'] }}</code>
                        </div>
                        <div class="text-muted small">
                            @if ($failure['http_status']) HTTP {{ $failure['http_status'] }} · @endif
                            {{ $failure['stage'] === 'local_validation' ? 'Validasi lokal' : 'Ditolak SATUSEHAT' }}
                        </div>
                    </div>
                    @if ($failure['element'])
                        <div class="small text-muted mt-1">Elemen FHIR: <code>{{ $failure['element'] }}</code></div>
                    @endif
                    <div class="small text-danger mt-1">{{ $failure['error'] }}</div>
                </div>
            @endforeach
        </div>
        <p class="text-muted small mb-0 mt-2">Perbaiki payload/data sumber, lalu gunakan tombol kirim ulang pada kunjungan terkait.</p>
    </div>
@endif

@if ($detail['skipped'] !== [])
    <div class="mb-3">
        <div class="fw-semibold mb-2">Tidak ikut terkirim</div>
        <ul class="mb-0 ps-3">
            @foreach ($detail['skipped'] as $note)
                <li class="mb-1">{{ $note }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="fw-semibold mb-2">Resource</div>
<div class="border rounded">
    @foreach ($detail['resources'] as $resource)
        <div class="@if (! $loop->last) border-bottom @endif">
            <div class="d-flex justify-content-between align-items-center gap-2 px-3 py-2">
                <span class="fw-semibold">{{ $resource['type'] }}</span>
                <span class="{{ $resource['badge'] }}">{{ $resource['label'] }}</span>
            </div>
            @if ($resource['items'] !== [])
                <div class="px-3 pb-2">
                    @foreach ($resource['items'] as $step)
                        <div class="d-flex gap-2 py-1">
                            <i class="bx {{ $step['ok'] ? 'bx-check-circle text-success' : 'bx-x-circle text-danger' }}"></i>
                            <div>
                                <div class="small">{{ $step['title'] }}</div>
                                <div class="text-muted small">
                                    {{ $step['result'] }}
                                    @if (! empty($step['http_status'])) · HTTP {{ $step['http_status'] }}@endif
                                    @if (! empty($step['local_key'])) · <code>{{ $step['local_key'] }}</code>@endif
                                </div>
                                @if (! empty($step['element']))
                                    <div class="small text-muted">Elemen: <code>{{ $step['element'] }}</code></div>
                                @endif
                                @if ($step['note'])
                                    <div class="small text-danger">{{ $step['note'] }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</div>

<details class="mt-3">
    <summary class="small text-muted">Data teknis</summary>
    <div class="row g-2 mt-2">
        <div class="col-md-6">
            <div class="text-muted small mb-1">Data yang dikirim</div>
            <pre class="small mb-0 bg-light p-2 rounded" style="max-height:240px;overflow:auto">{{ $detail['request_json'] }}</pre>
        </div>
        <div class="col-md-6">
            <div class="text-muted small mb-1">Jawaban SATUSEHAT</div>
            <pre class="small mb-0 bg-light p-2 rounded" style="max-height:240px;overflow:auto">{{ $detail['response_json'] }}</pre>
        </div>
    </div>
</details>
