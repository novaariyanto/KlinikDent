@php
    $canManageOdo = $writable && auth()->user()?->can('odontogram.manage');
    $findings = $teeth->filter(fn ($tooth) => \App\Support\Clinical\ToothConditions::fromRecord($tooth) !== [])->sortBy('tooth_number');
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
                @include('clinical.care.partials.odontogram-finding', ['tooth' => $tooth, 'canManageOdo' => $canManageOdo])
            @empty
                <div class="odo-board__empty" data-odo-empty>
                    Belum ada temuan. Klik gigi pada chart untuk mencatat kondisi.
                </div>
            @endforelse
        </div>
    </section>
</div>
