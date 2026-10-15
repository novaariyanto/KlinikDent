<section class="care-section" id="satusehat">
    <div class="care-section__head">
        <h5 class="care-section__title">SATUSEHAT</h5>
        <p class="care-section__hint">Riwayat data kunjungan ini yang dikirim ke SATUSEHAT. Kiriman terbaru di atas.</p>
    </div>
    <div class="care-section__body">
        @forelse ($satusehatLogs as $detail)
            <div class="border rounded mb-3">
                <button
                    class="btn w-100 text-start d-flex justify-content-between align-items-center gap-2 px-3 py-2"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#ss-visit-log-{{ $loop->index }}"
                    aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                >
                    <span>
                        <span class="fw-semibold">{{ $detail['when'] }}</span>
                        <span class="text-muted">· {{ $detail['headline'] }}</span>
                    </span>
                    <span class="{{ $detail['status']->badgeClass() }}">{{ $detail['status']->label() }}</span>
                </button>
                <div class="collapse @if ($loop->first) show @endif" id="ss-visit-log-{{ $loop->index }}">
                    <div class="border-top p-3">
                        @include('integrations.partials.log-detail', ['detail' => $detail])
                    </div>
                </div>
            </div>
        @empty
            <p class="text-muted mb-0">Belum ada pengiriman SATUSEHAT untuk kunjungan ini.</p>
        @endforelse
    </div>
</section>
