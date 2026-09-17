@php
    $activeVisit = $visit ?? null;
    $queues = $careQueues ?? collect();
@endphp

<aside class="doctor-sidebar" aria-label="Antrean pelayanan">
    <div class="doctor-sidebar__header">
        <a href="{{ route('dashboard') }}" class="doctor-sidebar__home">
            <i class="bx bx-left-arrow-alt" aria-hidden="true"></i>
            <span>Menu Utama</span>
        </a>
    </div>

    <div class="doctor-sidebar__search">
        <label class="visually-hidden" for="care-queue-search">Cari nama / No. RM</label>
        <div class="doctor-sidebar__search-wrap">
            <i class="bx bx-search" aria-hidden="true"></i>
            <input
                type="search"
                id="care-queue-search"
                class="form-control form-control-sm"
                placeholder="Cari nama / No. RM..."
                autocomplete="off"
            >
        </div>
    </div>

    <div class="doctor-sidebar__queue">
        <div class="doctor-sidebar__queue-head">
            <span>Antrean Hari Ini</span>
            <span class="badge bg-light text-muted">{{ $queues->count() }}</span>
        </div>

        <div class="doctor-sidebar__list" data-queue-list>
            @forelse ($queues as $item)
                @php
                    $isActive = $activeVisit && (int) $item->id === (int) $activeVisit->id;
                    $search = mb_strtolower(trim(
                        ($item->patient?->name ?? '').' '.
                        ($item->patient?->medical_record_number ?? '').' '.
                        ($item->queue?->displayNumber() ?? '')
                    ));
                @endphp
                <a
                    href="{{ route('care.show', array_filter(['visit' => $item, 'tab' => $section ?? null])) }}"
                    class="patient-queue-item {{ $isActive ? 'is-active' : '' }}"
                    data-queue-item
                    data-search="{{ $search }}"
                    @if ($isActive) aria-current="page" @endif
                >
                    <div class="patient-queue-item__row">
                        <span class="patient-queue-item__number">{{ $item->queue?->displayNumber() ?: '-' }}</span>
                        <span class="{{ $item->status->badgeClass() }} patient-queue-item__status">{{ $item->status->workspaceLabel() }}</span>
                    </div>
                    <div class="patient-queue-item__name">{{ $item->patient?->name ?: 'Pasien' }}</div>
                    <div class="patient-queue-item__rm">RM {{ $item->patient?->medical_record_number ?: '-' }}</div>
                </a>
            @empty
                <p class="doctor-sidebar__empty" data-queue-empty>Tidak ada antrean pasien hari ini.</p>
            @endforelse

            <p class="doctor-sidebar__empty" data-queue-miss hidden>
                Pasien tidak ditemukan.
                <span>Coba cari berdasarkan nama atau No. RM.</span>
            </p>
        </div>
    </div>
</aside>
