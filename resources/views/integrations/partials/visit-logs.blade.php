<div class="small text-muted mb-3">
    Kunjungan #{{ $visit->id }}
    @if ($visit->satusehat_id)
        · Encounter <code>{{ $visit->satusehat_id }}</code>
    @endif
</div>

@forelse ($logs as $detail)
    <div class="border rounded mb-3">
        <button
            class="btn w-100 text-start d-flex justify-content-between align-items-center gap-2 px-3 py-2"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#ss-visit-log-{{ $visit->id }}-{{ $loop->index }}"
            aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
        >
            <span>
                <span class="fw-semibold">{{ $detail['when'] }}</span>
                <span class="text-muted">· {{ $detail['headline'] }}</span>
            </span>
            <span class="{{ $detail['status']->badgeClass() }}">{{ $detail['status']->label() }}</span>
        </button>
        <div class="collapse @if ($loop->first) show @endif" id="ss-visit-log-{{ $visit->id }}-{{ $loop->index }}">
            <div class="border-top p-3">
                @include('integrations.partials.log-detail', ['detail' => $detail])
            </div>
        </div>
    </div>
@empty
    <p class="text-muted mb-0">Belum ada log SATUSEHAT untuk kunjungan ini.</p>
@endforelse
