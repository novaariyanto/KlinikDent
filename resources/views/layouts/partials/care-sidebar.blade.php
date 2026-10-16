@php
    $activeVisit = $visit ?? null;
    $queues = $careQueues ?? collect();
    $openQueues = $queues->filter(fn ($item) => $item->status !== \App\Enums\VisitStatus::Done)->values();
    $doneQueues = $queues->filter(fn ($item) => $item->status === \App\Enums\VisitStatus::Done)->values();
    $defaultTab = ($activeVisit?->status === \App\Enums\VisitStatus::Done) ? 'done' : 'open';
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

    <div class="doctor-sidebar__queue" data-queue-tabs>
        <div class="doctor-sidebar__filters" role="tablist" aria-label="Filter status antrean">
            <button
                type="button"
                class="doctor-sidebar__filter{{ $defaultTab === 'open' ? ' is-active' : '' }}"
                role="tab"
                id="care-queue-tab-open"
                data-queue-tab="open"
                aria-controls="care-queue-panel-open"
                aria-selected="{{ $defaultTab === 'open' ? 'true' : 'false' }}"
            >
                Belum selesai
                <span class="doctor-sidebar__filter-count">{{ $openQueues->count() }}</span>
            </button>
            <button
                type="button"
                class="doctor-sidebar__filter{{ $defaultTab === 'done' ? ' is-active' : '' }}"
                role="tab"
                id="care-queue-tab-done"
                data-queue-tab="done"
                aria-controls="care-queue-panel-done"
                aria-selected="{{ $defaultTab === 'done' ? 'true' : 'false' }}"
            >
                Selesai
                <span class="doctor-sidebar__filter-count">{{ $doneQueues->count() }}</span>
            </button>
        </div>

        @foreach ([
            'open' => ['items' => $openQueues, 'empty' => 'Tidak ada pasien belum selesai hari ini.'],
            'done' => ['items' => $doneQueues, 'empty' => 'Belum ada pasien selesai hari ini.'],
        ] as $tabKey => $tab)
            <div
                class="doctor-sidebar__list"
                id="care-queue-panel-{{ $tabKey }}"
                role="tabpanel"
                aria-labelledby="care-queue-tab-{{ $tabKey }}"
                data-queue-panel="{{ $tabKey }}"
                data-queue-list
                @if ($defaultTab !== $tabKey) hidden @endif
            >
                @forelse ($tab['items'] as $item)
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
                        class="patient-queue-item{{ $isActive ? ' is-active' : '' }}"
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
                    <p class="doctor-sidebar__empty" data-queue-empty>{{ $tab['empty'] }}</p>
                @endforelse

                <p class="doctor-sidebar__empty" data-queue-miss hidden>
                    Pasien tidak ditemukan.
                    <span>Coba cari berdasarkan nama atau No. RM.</span>
                </p>
            </div>
        @endforeach
    </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-queue-tabs]');
    if (! root) return;

    var search = document.getElementById('care-queue-search');
    var tabs = root.querySelectorAll('[data-queue-tab]');
    var panels = root.querySelectorAll('[data-queue-panel]');

    function activePanel() {
        return root.querySelector('[data-queue-panel]:not([hidden])');
    }

    function filterActivePanel() {
        var panel = activePanel();
        if (! panel) return;
        var q = (search && search.value ? search.value : '').trim().toLowerCase();
        var items = panel.querySelectorAll('[data-queue-item]');
        var miss = panel.querySelector('[data-queue-miss]');
        var empty = panel.querySelector('[data-queue-empty]');
        var shown = 0;

        items.forEach(function (item) {
            var on = ! q || (item.dataset.search || '').indexOf(q) !== -1;
            item.hidden = ! on;
            if (on) shown += 1;
        });

        if (miss) miss.hidden = ! (q && shown === 0);
        if (empty) empty.hidden = q !== '' || items.length > 0;
    }

    function showTab(key) {
        tabs.forEach(function (tab) {
            var on = tab.getAttribute('data-queue-tab') === key;
            tab.classList.toggle('is-active', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panels.forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-queue-panel') !== key;
        });
        filterActivePanel();
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            showTab(tab.getAttribute('data-queue-tab'));
        });
    });

    if (search) search.addEventListener('input', filterActivePanel);
    filterActivePanel();
});
</script>
