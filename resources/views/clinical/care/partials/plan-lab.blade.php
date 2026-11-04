@php
    $embedded = $embedded ?? false;
    $canManageLab = ($writable ?? false) && auth()->user()?->can('procedure.manage');
    $reopenLabCreate = old('_form') === 'lab-create' && $errors->any();
    $reopenLabEdit = old('_form') === 'lab-edit' && $errors->any();
    $labValue = function (string $form, string $key, mixed $default = '') {
        return old('_form') === $form ? old($key, $default) : $default;
    };
@endphp
<section class="care-section{{ $embedded ? ' care-section--embedded' : '' }}" id="lab">
    @unless ($embedded)
        <div class="care-section__head d-flex justify-content-between align-items-center gap-2">
            <div>
                <h5 class="care-section__title">Lab gigi</h5>
                <p class="care-section__hint">Pesanan kerja laboratorium untuk kunjungan ini.</p>
            </div>
            @if ($canManageLab)
                <x-button type="button" data-bs-toggle="modal" data-bs-target="#soap-lab-create-modal" icon="bx bx-plus">Tambah</x-button>
            @endif
        </div>
    @endunless
    <div class="care-section__body">
        @if ($embedded && $canManageLab)
            <div class="d-flex justify-content-end mb-2">
                <x-button type="button" data-bs-toggle="modal" data-bs-target="#soap-lab-create-modal" icon="bx bx-plus">Tambah</x-button>
            </div>
        @endif
        <x-table>
            <thead>
                <tr>
                    <th>Jenis</th>
                    <th>Gigi</th>
                    <th>Warna</th>
                    <th>Laboratorium</th>
                    <th>Jatuh tempo</th>
                    <th>Status</th>
                    @if ($canManageLab)
                        <th class="text-end">Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($labOrders ?? [] as $order)
                    <tr data-lab-row data-lab-id="{{ $order->id }}" data-work-type="{{ $order->work_type }}" data-tooth-numbers="{{ $order->tooth_numbers }}">
                        <td>{{ $order->work_type }}</td>
                        <td>{{ $order->tooth_numbers ?: '—' }}</td>
                        <td>{{ $order->shade ?: '—' }}</td>
                        <td>{{ $order->vendor ?: '—' }}</td>
                        <td>{{ $order->due_date?->format('d M Y') ?: '—' }}</td>
                        <td><span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                        @if ($canManageLab)
                            <td class="text-end text-nowrap">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-soft-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#soap-lab-edit-modal"
                                    data-lab-action="{{ route('care.lab.update', [$visit, $order]) }}"
                                    data-work-type="{{ $order->work_type }}"
                                    data-tooth-numbers="{{ $order->tooth_numbers }}"
                                    data-shade="{{ $order->shade }}"
                                    data-vendor="{{ $order->vendor }}"
                                    data-due-date="{{ $order->due_date?->format('Y-m-d') }}"
                                    data-status="{{ $order->status->value }}"
                                    data-order-id="{{ $order->id }}"
                                >Ubah</button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-soft-danger"
                                    data-bs-toggle="modal"
                                    data-bs-target="#soap-lab-delete-modal"
                                    data-lab-action="{{ route('care.lab.destroy', [$visit, $order]) }}"
                                    data-lab-label="{{ $order->work_type }}"
                                >Hapus</button>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr data-lab-empty><td colspan="{{ $canManageLab ? 7 : 6 }}" class="text-muted">Belum ada pesanan penunjang.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>
</section>

@if ($canManageLab)
    <x-modal id="soap-lab-create-modal" title="Tambah penunjang" size="modal-lg" class="care-modal" :data-show-on-load="$reopenLabCreate ? '' : null">
        <form id="soap-lab-create-form" method="POST" action="{{ route('care.lab.store', $visit) }}" class="care-form">
            @csrf
            <input type="hidden" name="_form" value="lab-create">
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label" for="lab-create-work-type">Jenis kerja <span class="text-danger">*</span></label>
                    <input class="form-control @if ($reopenLabCreate && $errors->has('work_type')) is-invalid @endif" name="work_type" id="lab-create-work-type" value="{{ $labValue('lab-create', 'work_type') }}" placeholder="Crown porselen" maxlength="120" required>
                    @if ($reopenLabCreate)
                        @error('work_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @endif
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="lab-create-tooth">Gigi</label>
                    <input class="form-control" name="tooth_numbers" id="lab-create-tooth" value="{{ $labValue('lab-create', 'tooth_numbers') }}" maxlength="80">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="lab-create-shade">Warna</label>
                    <input class="form-control" name="shade" id="lab-create-shade" value="{{ $labValue('lab-create', 'shade') }}" maxlength="40">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="lab-create-vendor">Laboratorium</label>
                    <input class="form-control" name="vendor" id="lab-create-vendor" value="{{ $labValue('lab-create', 'vendor') }}" maxlength="120">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="lab-create-due">Jatuh tempo</label>
                    <input type="date" class="form-control" name="due_date" id="lab-create-due" value="{{ $labValue('lab-create', 'due_date') }}">
                </div>
            </div>
        </form>
        <x-slot:footer>
            <x-button variant="secondary" data-bs-dismiss="modal">Batal</x-button>
            <x-button type="submit" form="soap-lab-create-form" icon="bx bx-save">Simpan</x-button>
        </x-slot:footer>
    </x-modal>

    <x-modal id="soap-lab-edit-modal" title="Ubah penunjang" size="modal-lg" class="care-modal" :data-show-on-load="$reopenLabEdit ? '' : null">
        <form id="soap-lab-edit-form" method="POST" action="{{ $reopenLabEdit ? route('care.lab.update', [$visit, (int) old('lab_order_id')]) : '#' }}" class="care-form">
            @csrf
            @method('PUT')
            <input type="hidden" name="_form" value="lab-edit">
            <input type="hidden" name="lab_order_id" id="lab-edit-id" value="{{ $labValue('lab-edit', 'lab_order_id') }}">
            <div class="row g-2">
                <div class="col-md-6">
                    <label class="form-label" for="lab-edit-work-type">Jenis kerja <span class="text-danger">*</span></label>
                    <input class="form-control @if ($reopenLabEdit && $errors->has('work_type')) is-invalid @endif" name="work_type" id="lab-edit-work-type" value="{{ $labValue('lab-edit', 'work_type') }}" maxlength="120" required>
                    @if ($reopenLabEdit)
                        @error('work_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @endif
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="lab-edit-tooth">Gigi</label>
                    <input class="form-control" name="tooth_numbers" id="lab-edit-tooth" value="{{ $labValue('lab-edit', 'tooth_numbers') }}" maxlength="80">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="lab-edit-shade">Warna</label>
                    <input class="form-control" name="shade" id="lab-edit-shade" value="{{ $labValue('lab-edit', 'shade') }}" maxlength="40">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="lab-edit-vendor">Laboratorium</label>
                    <input class="form-control" name="vendor" id="lab-edit-vendor" value="{{ $labValue('lab-edit', 'vendor') }}" maxlength="120">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="lab-edit-due">Jatuh tempo</label>
                    <input type="date" class="form-control" name="due_date" id="lab-edit-due" value="{{ $labValue('lab-edit', 'due_date') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="lab-edit-status">Status</label>
                    <select name="status" id="lab-edit-status" class="form-select">
                        @foreach (\App\Enums\LabOrderStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected($labValue('lab-edit', 'status', \App\Enums\LabOrderStatus::Ordered->value) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </form>
        <x-slot:footer>
            <x-button variant="secondary" data-bs-dismiss="modal">Batal</x-button>
            <x-button type="submit" form="soap-lab-edit-form" icon="bx bx-save">Simpan</x-button>
        </x-slot:footer>
    </x-modal>

    <x-modal id="soap-lab-delete-modal" title="Hapus penunjang" size="modal-md" class="care-modal">
        <form id="soap-lab-delete-form" method="POST" action="#">
            @csrf
            @method('DELETE')
            <p class="mb-0">Hapus pesanan <strong data-lab-delete-label>penunjang</strong>?</p>
        </form>
        <x-slot:footer>
            <x-button variant="secondary" data-bs-dismiss="modal">Batal</x-button>
            <x-button type="submit" form="soap-lab-delete-form" variant="danger" icon="bx bx-trash">Hapus</x-button>
        </x-slot:footer>
    </x-modal>
@endif

@once
@push('scripts')
<script>
(function () {
    var editModal = document.getElementById('soap-lab-edit-modal');
    var deleteModal = document.getElementById('soap-lab-delete-modal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (!button || !button.getAttribute('data-lab-action')) return;
            var form = document.getElementById('soap-lab-edit-form');
            if (!form) return;
            form.action = button.getAttribute('data-lab-action');
            var map = {
                'lab-edit-id': 'data-order-id',
                'lab-edit-work-type': 'data-work-type',
                'lab-edit-tooth': 'data-tooth-numbers',
                'lab-edit-shade': 'data-shade',
                'lab-edit-vendor': 'data-vendor',
                'lab-edit-due': 'data-due-date',
                'lab-edit-status': 'data-status'
            };
            Object.keys(map).forEach(function (id) {
                var field = document.getElementById(id);
                if (field) field.value = button.getAttribute(map[id]) || '';
            });
        });
    }
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            if (!button || !button.getAttribute('data-lab-action')) return;
            var form = document.getElementById('soap-lab-delete-form');
            var label = deleteModal.querySelector('[data-lab-delete-label]');
            if (form) form.action = button.getAttribute('data-lab-action');
            if (label) label.textContent = button.getAttribute('data-lab-label') || 'penunjang';
        });
    }

    function labText(value) {
        return value ? String(value) : '—';
    }

    function syncLabSummary() {
        var target = document.querySelector('[data-lab-summary]');
        if (!target) return;
        var bits = [];
        document.querySelectorAll('[data-lab-row]').forEach(function (row) {
            var type = row.getAttribute('data-work-type') || '';
            var tooth = row.getAttribute('data-tooth-numbers') || '';
            if (!type) return;
            bits.push(tooth ? type + ' (' + tooth + ')' : type);
        });
        target.textContent = bits.length ? bits.join('; ') : '—';
    }

    function labButton(className, label, target, attrs) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = className;
        button.setAttribute('data-bs-toggle', 'modal');
        button.setAttribute('data-bs-target', target);
        Object.keys(attrs).forEach(function (key) {
            button.setAttribute(key, attrs[key] || '');
        });
        button.textContent = label;
        return button;
    }

    function upsertLabRow(order) {
        var table = document.querySelector('#lab tbody');
        if (!table || !order) return;
        var empty = table.querySelector('[data-lab-empty]');
        if (empty) empty.remove();
        var row = table.querySelector('[data-lab-id="' + order.id + '"]');
        if (!row) {
            row = document.createElement('tr');
            row.setAttribute('data-lab-row', '');
            table.appendChild(row);
        }
        row.setAttribute('data-lab-id', order.id);
        row.setAttribute('data-work-type', order.work_type || '');
        row.setAttribute('data-tooth-numbers', order.tooth_numbers || '');
        row.replaceChildren();
        [order.work_type, order.tooth_numbers, order.shade, order.vendor, order.due_label].forEach(function (value) {
            var cell = document.createElement('td');
            cell.textContent = labText(value);
            row.appendChild(cell);
        });
        var statusCell = document.createElement('td');
        var badge = document.createElement('span');
        badge.className = order.status_class || 'badge bg-secondary';
        badge.textContent = order.status_label || '';
        statusCell.appendChild(badge);
        row.appendChild(statusCell);
        if (document.getElementById('soap-lab-edit-modal')) {
            var actions = document.createElement('td');
            actions.className = 'text-end text-nowrap';
            actions.appendChild(labButton('btn btn-sm btn-soft-primary', 'Ubah', '#soap-lab-edit-modal', {
                'data-lab-action': order.update_url,
                'data-work-type': order.work_type,
                'data-tooth-numbers': order.tooth_numbers,
                'data-shade': order.shade,
                'data-vendor': order.vendor,
                'data-due-date': order.due_date,
                'data-status': order.status,
                'data-order-id': order.id
            }));
            actions.appendChild(document.createTextNode(' '));
            actions.appendChild(labButton('btn btn-sm btn-soft-danger', 'Hapus', '#soap-lab-delete-modal', {
                'data-lab-action': order.destroy_url,
                'data-lab-label': order.work_type
            }));
            row.appendChild(actions);
        }
        syncLabSummary();
    }

    function removeLabRow(id) {
        var table = document.querySelector('#lab tbody');
        var row = table ? table.querySelector('[data-lab-id="' + id + '"]') : null;
        if (row) row.remove();
        if (table && !table.querySelector('[data-lab-row]')) {
            var empty = document.createElement('tr');
            empty.setAttribute('data-lab-empty', '');
            var cell = document.createElement('td');
            var columns = table.closest('table').querySelectorAll('thead th').length || 6;
            cell.colSpan = columns;
            cell.className = 'text-muted';
            cell.textContent = 'Belum ada pesanan penunjang.';
            empty.appendChild(cell);
            table.appendChild(empty);
        }
        syncLabSummary();
    }

    function hideLabModal(form) {
        var modal = form.closest('.modal');
        if (modal && window.bootstrap) window.bootstrap.Modal.getOrCreateInstance(modal).hide();
    }

    document.querySelectorAll('#soap-lab-create-form, #soap-lab-edit-form, #soap-lab-delete-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (!form.action || form.action.slice(-1) === '#') return;
            var body = new FormData(form);
            if (window.careSetStatus) window.careSetStatus('Menyimpan…');
            fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': body.get('_token') || ''
                },
                body: body
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (data) {
                    if (!res.ok) {
                        var message = window.careValidationMessage ? window.careValidationMessage(data) : 'Gagal menyimpan';
                        throw new Error(message);
                    }
                    return data;
                });
            }).then(function (data) {
                if (data.deleted_id) removeLabRow(data.deleted_id);
                if (data.order) upsertLabRow(data.order);
                if (form.id === 'soap-lab-create-form') form.reset();
                hideLabModal(form);
                if (window.careSetStatus) window.careSetStatus('✓ Tersimpan');
            }).catch(function (error) {
                if (window.careSetStatus) window.careSetStatus(error.message || 'Gagal menyimpan', true);
            });
        });
    });
})();
</script>
@endpush
@endonce
