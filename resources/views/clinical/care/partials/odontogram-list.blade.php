@php
    $canManageOdo = $writable && auth()->user()?->can('odontogram.manage');
    $findings = $teeth->filter(fn ($tooth) => $tooth->status && $tooth->status !== \App\Enums\ToothStatus::Healthy)->sortBy('tooth_number');
@endphp

<div class="odo-board" data-odo-board data-odo-destroy="{{ url('/visits/'.$visit->id.'/care/tooth') }}">
    <section class="odo-board__panel">
        <header class="odo-board__head">
            <div>
                <h6>Temuan odontogram</h6>
                <p>Gigi yang sudah dicatat pada pasien ini.</p>
            </div>
            <span class="odo-board__count" data-odo-count>{{ $findings->count() }}</span>
        </header>

        <div class="odo-finding-list" data-odo-findings>
            @forelse ($findings as $tooth)
                <article class="odo-finding" data-finding-row="{{ $tooth->tooth_number }}">
                    <div class="odo-finding__num">{{ $tooth->tooth_number }}</div>
                    <div class="odo-finding__body">
                        <div class="odo-finding__status">
                            <i style="background: {{ $tooth->status->color() }}"></i>
                            <strong>{{ $tooth->status->label() }}</strong>
                            @if ($tooth->surfaces)
                                <span>{{ \App\Enums\ToothSurface::shortList($tooth->surfaces) }}</span>
                            @endif
                        </div>
                        @if ($tooth->notes)
                            <p class="odo-finding__notes">{{ $tooth->notes }}</p>
                        @endif
                    </div>
                    @if ($canManageOdo)
                        <div class="odo-finding__actions">
                            <button type="button" class="btn btn-sm btn-soft-primary" data-odo-edit="{{ $tooth->tooth_number }}">Ubah</button>
                            <button type="button" class="btn btn-sm btn-soft-danger" data-odo-delete="{{ $tooth->tooth_number }}">Hapus</button>
                        </div>
                    @endif
                </article>
            @empty
                <div class="odo-board__empty" data-odo-empty>
                    Belum ada temuan. Klik gigi pada chart untuk mencatat kondisi.
                </div>
            @endforelse
        </div>
    </section>
</div>
