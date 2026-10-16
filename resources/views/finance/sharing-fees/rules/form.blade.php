@extends('layouts.app')

@php
    $isEdit = $rule->exists;
    $selectedDoctor = old('doctor_user_id', $rule->doctor_user_id ?? $scopeAll);
    $selectedBranch = old('branch_id', $rule->branch_id ?? $scopeAll);
    $selectedProcedure = old('procedure_id', $rule->procedure_id ?? $scopeAll);
    if ($selectedDoctor === null || $selectedDoctor === '') { $selectedDoctor = $scopeAll; }
    if ($selectedBranch === null || $selectedBranch === '') { $selectedBranch = $scopeAll; }
    if ($selectedProcedure === null || $selectedProcedure === '') { $selectedProcedure = $scopeAll; }
@endphp

@section('title', $isEdit ? 'Edit Aturan Sharing Fee' : 'Tambah Aturan Sharing Fee')

@push('styles')
    <link href="{{ theme('libs/select2/css/select2.min.css') }}" rel="stylesheet" type="text/css">
@endpush

@section('content')
    <x-page-header
        :title="$isEdit ? 'Edit Aturan Sharing Fee' : 'Tambah Aturan Sharing Fee'"
        :breadcrumb="['Keuangan' => route('finance.index'), 'Aturan Fee' => route('finance.sharing-fees.rules.index'), ($isEdit ? 'Edit' : 'Tambah') => null]"
    />
    <x-alert />

    <div class="row">
        <div class="col-lg-7">
            <x-card title="Form aturan">
                <form
                    id="sharing-fee-rule-form"
                    method="POST"
                    action="{{ $isEdit ? route('finance.sharing-fees.rules.update', $rule) : route('finance.sharing-fees.rules.store') }}"
                    class="row g-3"
                >
                    @csrf
                    @if ($isEdit)
                        @method('PUT')
                    @endif

                    <div class="col-12">
                        <div class="alert alert-info mb-0 py-2">
                            Ringkasan cakupan: <strong id="coverage-summary">—</strong>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="doctor_user_id">Dokter <span class="text-danger">*</span></label>
                        <select name="doctor_user_id" id="doctor_user_id" class="form-select js-sf-select" required>
                            @foreach ($doctorOptions as $value => $label)
                                <option value="{{ $value }}" @selected((string) $selectedDoctor === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('doctor_user_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="branch_id">Cabang <span class="text-danger">*</span></label>
                        <select name="branch_id" id="branch_id" class="form-select js-sf-select" required>
                            @foreach ($branchOptions as $value => $label)
                                <option value="{{ $value }}" @selected((string) $selectedBranch === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('branch_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="procedure_id">Tindakan <span class="text-danger">*</span></label>
                        <select name="procedure_id" id="procedure_id" class="form-select js-sf-select" required>
                            @foreach ($procedureOptions as $value => $label)
                                <option value="{{ $value }}" @selected((string) $selectedProcedure === (string) $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('procedure_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <x-select name="calc_type" id="calc_type" label="Jenis perhitungan" :options="$calcTypes" :selected="old('calc_type', $rule->calc_type?->value ?? $rule->calc_type)" :placeholder="false" required />
                    </div>
                    <div class="col-md-4">
                        <x-input name="rate" id="rate" label="Tarif (% atau nominal)" type="number" step="0.0001" :value="old('rate', $rule->rate)" required />
                    </div>
                    <div class="col-md-4">
                        <x-input name="priority" label="Prioritas (lebih tinggi menang pada tingkat sama)" type="number" :value="old('priority', $rule->priority)" required />
                    </div>

                    <div class="col-md-4">
                        <x-select name="material_deduction_type" id="material_deduction_type" label="Potongan bahan" :options="$deductionTypes" :selected="old('material_deduction_type', $rule->material_deduction_type?->value ?? $rule->material_deduction_type)" :placeholder="false" required />
                    </div>
                    <div class="col-md-4">
                        <x-input name="material_deduction_value" id="material_deduction_value" label="Nilai potongan" type="number" step="0.0001" :value="old('material_deduction_value', $rule->material_deduction_value)" />
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $rule->is_active))>
                            <label class="form-check-label" for="is_active">Aktif</label>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <x-input name="effective_from" id="effective_from" label="Berlaku dari" type="date" :value="old('effective_from', optional($rule->effective_from)->toDateString() ?? $rule->effective_from)" required />
                    </div>
                    <div class="col-md-6">
                        <x-input name="effective_to" label="Berlaku sampai (opsional)" type="date" :value="old('effective_to', optional($rule->effective_to)->toDateString())" />
                    </div>
                    <div class="col-12">
                        <x-input name="notes" label="Catatan" :value="old('notes', $rule->notes)" />
                    </div>
                    <div class="col-12 d-flex gap-2">
                        <x-button type="submit" icon="bx bx-save">Simpan</x-button>
                        <a href="{{ route('finance.sharing-fees.rules.index') }}" class="btn btn-light">Batal</a>
                    </div>
                </form>
            </x-card>
        </div>

        <div class="col-lg-5">
            <x-card title="Preview perhitungan">
                <p class="text-muted small">Menggunakan algoritma yang sama dengan generate settlement.</p>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label" for="preview_gross">Subtotal item</label>
                        <input type="number" step="0.01" id="preview_gross" class="form-control" value="250000">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="preview_qty">Kuantitas</label>
                        <input type="number" min="1" id="preview_qty" class="form-control" value="1">
                    </div>
                    <div class="col-12">
                        <button type="button" id="btn-preview" class="btn btn-soft-primary btn-sm">Hitung preview</button>
                    </div>
                </div>
                <div id="preview-result" class="mt-3 small">
                    <p class="text-muted mb-0">Isi cakupan lalu klik hitung preview.</p>
                </div>
                <hr>
                <p class="small text-muted mb-0">
                    Fee persentase = (subtotal − potongan) × tarif / 100.<br>
                    Fee nominal tetap = tarif × kuantitas (potongan bahan tidak mengurangi nominal fee).
                </p>
            </x-card>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ theme('libs/select2/js/select2.min.js') }}"></script>
    <script>
        (function () {
            var scopeAll = @json($scopeAll);
            var previewUrl = @json($previewUrl);
            var csrf = @json(csrf_token());

            function labelOf(id) {
                var el = document.getElementById(id);
                if (!el) return '—';
                var opt = el.options[el.selectedIndex];
                return opt ? opt.text : '—';
            }

            function updateCoverage() {
                var summary = labelOf('doctor_user_id') + ' · ' + labelOf('branch_id') + ' · ' + labelOf('procedure_id');
                var target = document.getElementById('coverage-summary');
                if (target) target.textContent = summary;
            }

            function formatMoney(v) {
                var n = Number(v || 0);
                return n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function runPreview() {
                var box = document.getElementById('preview-result');
                box.innerHTML = '<p class="text-muted mb-0">Menghitung…</p>';

                var body = new FormData();
                body.append('_token', csrf);
                body.append('doctor_user_id', document.getElementById('doctor_user_id').value);
                body.append('branch_id', document.getElementById('branch_id').value);
                body.append('procedure_id', document.getElementById('procedure_id').value);
                body.append('on_date', document.getElementById('effective_from').value || new Date().toISOString().slice(0, 10));
                body.append('gross_base', document.getElementById('preview_gross').value || '0');
                body.append('quantity', document.getElementById('preview_qty').value || '1');

                fetch(previewUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: body
                }).then(function (r) { return r.json(); }).then(function (data) {
                    if (!data.matched) {
                        box.innerHTML = '<div class="alert alert-warning mb-0 py-2"><strong>' + (data.message || 'Belum memiliki aturan Sharing Fee') + '</strong></div>';
                        return;
                    }
                    var rule = data.rule || {};
                    var calc = data.calculation || {};
                    var html = '';
                    html += '<p class="mb-1"><strong>Aturan #' + rule.id + '</strong> — ' + (rule.coverage || '') + '</p>';
                    html += '<p class="mb-1">Jenis: ' + (rule.calc_type_label || '') + ' · Tarif: ' + rule.rate + ' · Prioritas: ' + rule.priority + '</p>';
                    html += '<ul class="mb-1 ps-3">';
                    html += '<li>Basis kotor: Rp ' + formatMoney(calc.gross_base) + '</li>';
                    html += '<li>Potongan: Rp ' + formatMoney(calc.material_deduction) + '</li>';
                    html += '<li>Basis fee: Rp ' + formatMoney(calc.fee_base) + '</li>';
                    html += '<li>Qty: ' + (calc.quantity || 1) + '</li>';
                    html += '<li><strong>Fee: Rp ' + formatMoney(calc.fee_amount) + '</strong></li>';
                    html += '</ul>';
                    if (rule.note_fixed) {
                        html += '<p class="text-muted mb-0">' + rule.note_fixed + '</p>';
                    }
                    box.innerHTML = html;
                }).catch(function () {
                    box.innerHTML = '<div class="alert alert-danger mb-0 py-2">Gagal menghitung preview.</div>';
                });
            }

            if (window.jQuery && jQuery.fn.select2) {
                jQuery('.js-sf-select').select2({ width: '100%', placeholder: 'Pilih…' });
                jQuery('.js-sf-select').on('change', updateCoverage);
            } else {
                ['doctor_user_id', 'branch_id', 'procedure_id'].forEach(function (id) {
                    var el = document.getElementById(id);
                    if (el) el.addEventListener('change', updateCoverage);
                });
            }

            updateCoverage();
            var btn = document.getElementById('btn-preview');
            if (btn) btn.addEventListener('click', runPreview);
        })();
    </script>
@endpush
