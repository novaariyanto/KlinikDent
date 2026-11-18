@extends('layouts.app')

@section('title', 'Semua Artikel')

@section('content')
    <x-page-header title="Semua Artikel" :breadcrumb="['CMS' => route('cms.dashboard'), 'Artikel' => null]" />
    <x-alert />

    <x-card title="Artikel blog">
        <x-slot:header-actions>
            @can('cms.manage')
                <x-button href="{{ route('cms.posts.create') }}" icon="bx bx-plus">Tambah Artikel</x-button>
            @endcan
        </x-slot:header-actions>

        <div class="row g-2 align-items-end mb-3">
            <div class="col-md-3">
                <label class="form-label mb-1" for="cms-status-filter">Filter status</label>
                <select id="cms-status-filter" class="form-select form-select-sm">
                    <option value="">Semua status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            @can('cms.manage')
                <div class="col-md-3">
                    <label class="form-label mb-1" for="cms-bulk-status">Ubah status terpilih</label>
                    <select id="cms-bulk-status" class="form-select form-select-sm">
                        <option value="">Pilih status…</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-auto">
                    <button type="button" id="cms-bulk-apply" class="btn btn-sm btn-primary" disabled>
                        Terapkan (<span id="cms-selected-count">0</span>)
                    </button>
                </div>
            @endcan
        </div>

        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="cms-check-all">
            <label class="form-check-label" for="cms-check-all">Pilih semua di halaman ini</label>
        </div>

        <div class="skote-datatable-wrapper">
            <table
                id="cms-posts-table"
                class="table table-bordered dt-responsive nowrap w-100 skote-datatable"
                data-columns="{{ json_encode($columns) }}"
                data-order="{{ json_encode([[6, 'desc']]) }}"
                data-page-length="25"
            >
                <thead>
                    <tr>
                        @foreach ($columns as $column)
                            <th @class([$column['className'] ?? '']) @if (! empty($column['width'])) style="width: {{ $column['width'] }}" @endif>
                                {{ $column['title'] ?? $column['data'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </x-card>
@endsection

@push('scripts')
<script>
(function () {
    function boot() {
        var tableEl = document.getElementById('cms-posts-table');
        if (!tableEl || typeof window.initServerDataTable !== 'function') {
            setTimeout(boot, 50);
            return;
        }

        var selected = new Set();
        var countEl = document.getElementById('cms-selected-count');
        var applyBtn = document.getElementById('cms-bulk-apply');
        var filterEl = document.getElementById('cms-status-filter');
        var bulkStatusEl = document.getElementById('cms-bulk-status');
        var checkAllEl = document.getElementById('cms-check-all');
        var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        function syncCount() {
            var n = selected.size;
            if (countEl) countEl.textContent = String(n);
            if (applyBtn) applyBtn.disabled = n === 0 || !bulkStatusEl || !bulkStatusEl.value;
        }

        function syncRowChecks() {
            tableEl.querySelectorAll('.cms-post-check').forEach(function (el) {
                el.checked = selected.has(String(el.value));
            });
            if (checkAllEl) {
                var boxes = tableEl.querySelectorAll('.cms-post-check');
                checkAllEl.checked = boxes.length > 0 && Array.prototype.every.call(boxes, function (el) {
                    return el.checked;
                });
            }
        }

        var dt = window.initServerDataTable(tableEl, {
            order: [[6, 'desc']],
            pageLength: 25,
            ajax: {
                url: @json(route('cms.posts.data')),
                type: 'GET',
                data: function (d) {
                    d.status = filterEl ? filterEl.value : '';
                },
                headers: csrf ? { 'X-CSRF-TOKEN': csrf } : {},
            },
            drawCallback: function () {
                syncRowChecks();
                syncCount();
            },
        });

        if (filterEl) {
            filterEl.addEventListener('change', function () {
                if (dt && dt.ajax) dt.ajax.reload();
            });
        }

        document.addEventListener('change', function (e) {
            if (!e.target) return;
            if (e.target.id === 'cms-check-all') {
                tableEl.querySelectorAll('.cms-post-check').forEach(function (el) {
                    if (e.target.checked) selected.add(String(el.value));
                    else selected.delete(String(el.value));
                    el.checked = e.target.checked;
                });
                syncCount();
                return;
            }
            if (e.target.classList && e.target.classList.contains('cms-post-check')) {
                if (e.target.checked) selected.add(String(e.target.value));
                else selected.delete(String(e.target.value));
                syncCount();
                if (checkAllEl) {
                    var boxes = tableEl.querySelectorAll('.cms-post-check');
                    checkAllEl.checked = boxes.length > 0 && Array.prototype.every.call(boxes, function (el) {
                        return el.checked;
                    });
                }
            }
            if (e.target.id === 'cms-bulk-status') syncCount();
        });

        if (applyBtn) {
            applyBtn.addEventListener('click', function () {
                var status = bulkStatusEl ? bulkStatusEl.value : '';
                if (!status || selected.size === 0) return;
                if (!window.confirm('Ubah status ' + selected.size + ' artikel terpilih?')) return;

                applyBtn.disabled = true;
                fetch(@json(route('cms.posts.bulk-status')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf || '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        ids: Array.from(selected).map(Number),
                        status: status,
                    }),
                }).then(function (res) {
                    return res.json().catch(function () { return {}; }).then(function (payload) {
                        if (!res.ok) throw new Error((payload && payload.message) || 'Gagal mengubah status');
                        selected.clear();
                        if (checkAllEl) checkAllEl.checked = false;
                        syncCount();
                        if (dt && dt.ajax) dt.ajax.reload(null, false);
                        window.alert(payload.message || 'Status diperbarui');
                    });
                }).catch(function (err) {
                    window.alert(err && err.message ? err.message : 'Gagal mengubah status');
                }).finally(function () {
                    syncCount();
                });
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
</script>
@endpush
