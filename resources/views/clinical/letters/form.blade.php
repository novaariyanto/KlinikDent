@extends('layouts.app')

@php
    $chrome = $chrome ?? [];
    $snapPatient = $snapshot['patient'] ?? [];
    $snapDoctor = $snapshot['doctor'] ?? [];
    $addressLine = collect($chrome['clinicAddressLines'] ?? [])->filter()->implode(', ');
    $contactLine = collect([$chrome['clinicPhone'] ?? null, $chrome['clinicEmail'] ?? null])->filter()->implode(' · ');
    $birth = trim(($snapPatient['birth_place'] ?? ($patient->birth_place ?: '-')).' / '.($snapPatient['dob'] ?? ($patient->dob?->format('Y-m-d') ?: '-')), ' /');
    $baseAddress = $snapPatient['address'] ?? '-';
@endphp

@section('title', $letter ? 'Revisi surat' : 'Buat surat keterangan')

@section('content')
    <x-page-header
        :title="$letter ? 'Revisi '.$letter->number : 'Buat Surat Keterangan'"
        :breadcrumb="[
            'Surat Keterangan' => route('medical-letters.index'),
            ($patient->name ?? 'Pasien') => route('care.show', $visit),
            'Form' => null,
        ]"
    />
    <x-alert />

    <x-card title="Identitas" class="mb-3">
        <div class="letter-identity">
            <div><span>Pasien</span><strong>{{ $snapPatient['name'] ?? $patient->name }}</strong></div>
            <div><span>No. RM</span><strong>{{ $snapPatient['medical_record_number'] ?? $patient->medical_record_number }}</strong></div>
            <div><span>NIK</span><strong>{{ $snapPatient['nik'] ?? ($patient->nik ?: '-') }}</strong></div>
            <div><span>Tempat / tgl lahir</span><strong>{{ $birth }}</strong></div>
            <div><span>Jenis kelamin</span><strong>{{ $snapPatient['gender'] ?? ($patient->gender?->label() ?: '-') }}</strong></div>
            <div><span>Pemeriksaan</span><strong>{{ $visit->visit_date?->format('d M Y') ?: '-' }}</strong></div>
            <div><span>Dokter</span><strong>{{ $snapDoctor['name'] ?? ($visit->doctor?->name ?: '-') }}</strong></div>
            <div><span>SIP</span><strong>{{ $snapDoctor['sip'] ?? ($visit->doctor?->doctorProfile?->sip ?: '-') }}</strong></div>
            <div><span>Cabang</span><strong>{{ $visit->branch?->name ?: '-' }}</strong></div>
            <div><span>Alamat</span><strong id="identity-address">{{ $baseAddress }}</strong></div>
        </div>
    </x-card>

    <form method="POST" action="{{ $letter ? route('medical-letters.revise.store', $letter) : route('care.letters.store', $visit) }}" id="letter-form">
        @csrf
        @if ($letter)
            <input type="hidden" name="revises_id" value="{{ $letter->id }}">
        @endif
        <div class="row g-3">
            <div class="col-lg-6">
                <x-card title="Isi surat">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="letter-type">Jenis surat</label>
                            <select name="type" id="letter-type" class="form-select" required>
                                @foreach ($types as $value => $label)
                                    <option value="{{ $value }}" @selected(old('type', $defaults['type']) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <x-input name="issued_on" type="date" label="Tanggal penerbitan" :value="old('issued_on', $defaults['issued_on'])" required />
                        </div>
                        <div class="col-md-6">
                            <x-input name="purpose" label="Keperluan surat" :value="old('purpose', $defaults['purpose'] ?? '')" />
                        </div>
                        <div class="col-md-6">
                            <x-input name="address_override" label="Koreksi alamat (opsional)" :value="old('address_override', $defaults['address_override'] ?? '')" />
                        </div>
                    </div>

                    <div class="mt-3" data-letter-panel="sick">
                        <div class="row g-3">
                            <div class="col-md-4"><x-input name="rest_from" type="date" label="Mulai sakit" :value="old('rest_from', $defaults['rest_from'])" /></div>
                            <div class="col-md-4"><x-input name="rest_until" type="date" label="Selesai istirahat" :value="old('rest_until', $defaults['rest_until'])" /></div>
                            <div class="col-md-4"><x-input name="rest_days" type="number" label="Jumlah hari" :value="old('rest_days', $defaults['rest_days'])" /></div>
                            <div class="col-12">
                                <label class="form-label" for="condition_note">Keterangan kondisi</label>
                                <textarea name="condition_note" id="condition_note" rows="2" class="form-control">{{ old('condition_note', $defaults['condition_note']) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3" data-letter-panel="healthy">
                        <label class="form-label" for="healthy_for">Keperluan sehat</label>
                        <select name="healthy_for" id="healthy_for" class="form-select mb-3">
                            @foreach (['pekerjaan' => 'Pekerjaan', 'sekolah' => 'Sekolah / pendidikan', 'pendaftaran' => 'Pendaftaran', 'lainnya' => 'Administrasi lainnya'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('healthy_for', $defaults['healthy_for']) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <label class="form-label" for="conclusion">Kesimpulan kondisi kesehatan</label>
                        <textarea name="conclusion" id="conclusion" rows="2" class="form-control">{{ old('conclusion', $defaults['conclusion']) }}</textarea>
                    </div>

                    <div class="mt-3" data-letter-panel="examination">
                        <label class="form-label" for="complaint">Keluhan</label>
                        <textarea name="complaint" id="complaint" rows="2" class="form-control">{{ old('complaint', $defaults['complaint']) }}</textarea>
                    </div>

                    <div class="mt-3" data-letter-panel="examination,healthy">
                        <label class="form-label" for="exam_result">Hasil pemeriksaan</label>
                        <textarea name="exam_result" id="exam_result" rows="3" class="form-control">{{ old('exam_result', $defaults['exam_result']) }}</textarea>
                    </div>

                    <div class="mt-3" data-letter-panel="radiograph">
                        <div class="row g-3">
                            <div class="col-md-4"><x-input name="radiograph_date" type="date" label="Tanggal rontgen" :value="old('radiograph_date', $defaults['radiograph_date'] ?? '')" /></div>
                            <div class="col-md-4">
                                <label class="form-label" for="radiograph_type">Jenis pemeriksaan</label>
                                <select name="radiograph_type" id="radiograph_type" class="form-select">
                                    @foreach (['periapikal' => 'Periapikal', 'panoramik' => 'Panoramik', 'sefalometri' => 'Sefalometri', 'oklusal' => 'Oklusal', 'bitewing' => 'Bitewing', 'lainnya' => 'Lainnya'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('radiograph_type', $defaults['radiograph_type'] ?? '') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4"><x-input name="radiograph_region" label="Regio / gigi" :value="old('radiograph_region', $defaults['radiograph_region'] ?? '')" /></div>
                            <div class="col-12">
                                <label class="form-label" for="radiograph_finding">Hasil pembacaan</label>
                                <textarea name="radiograph_finding" id="radiograph_finding" rows="3" class="form-control">{{ old('radiograph_finding', $defaults['radiograph_finding'] ?? '') }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="radiograph_conclusion">Kesimpulan</label>
                                <textarea name="radiograph_conclusion" id="radiograph_conclusion" rows="2" class="form-control">{{ old('radiograph_conclusion', $defaults['radiograph_conclusion'] ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3" data-letter-panel="treatment">
                        <div class="row g-3">
                            <div class="col-md-4"><x-input name="treatment_date" type="date" label="Tanggal perawatan" :value="old('treatment_date', $defaults['treatment_date'])" /></div>
                            <div class="col-12">
                                <label class="form-label" for="procedures_note">Jenis tindakan</label>
                                <textarea name="procedures_note" id="procedures_note" rows="2" class="form-control">{{ old('procedures_note', $defaults['procedures_note']) }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="follow_up">Tindak lanjut</label>
                                <textarea name="follow_up" id="follow_up" rows="2" class="form-control">{{ old('follow_up', $defaults['follow_up']) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3" data-letter-panel="control">
                        <div class="row g-3">
                            <div class="col-md-4"><x-input name="control_date" type="date" label="Tanggal kontrol" :value="old('control_date', $defaults['control_date'])" /></div>
                            <div class="col-12">
                                <label class="form-label" for="control_reason">Alasan kontrol</label>
                                <textarea name="control_reason" id="control_reason" rows="2" class="form-control">{{ old('control_reason', $defaults['control_reason']) }}</textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="doctor_note">Catatan dokter</label>
                                <textarea name="doctor_note" id="doctor_note" rows="2" class="form-control">{{ old('doctor_note', $defaults['doctor_note']) }}</textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3" data-letter-panel="other">
                        <x-input name="title" label="Judul surat" :value="old('title', $defaults['title'] ?? '')" />
                    </div>

                    <div class="mt-3">
                        <input type="hidden" name="diagnosis_scope" value="selected">
                        @php
                            $dxSource = old('diagnoses');
                            if (! is_array($dxSource)) {
                                $dxSource = $visit->diagnoses->map(fn ($diagnosis) => [
                                    'id' => $diagnosis->id,
                                    'selected' => true,
                                    'code' => $diagnosis->code,
                                    'description' => $diagnosis->description,
                                ])->all();
                            }
                            $dxItems = [];
                            foreach ($dxSource as $row) {
                                if (! is_array($row) || empty($row['selected']) || ! filled($row['description'] ?? null)) {
                                    continue;
                                }
                                $code = trim((string) ($row['code'] ?? ''));
                                $description = trim((string) $row['description']);
                                $key = $code !== '' ? $code : 'id:'.($row['id'] ?? md5($description));
                                $dxItems[$key] = [
                                    'key' => $key,
                                    'id' => (int) ($row['id'] ?? 0),
                                    'code' => $code,
                                    'description' => $description,
                                    'text' => trim(($code !== '' ? $code.' — ' : '').$description),
                                ];
                            }
                            $dxItems = array_values($dxItems);
                        @endphp
                        <label class="form-label" for="letter-dx">Diagnosa sementara</label>
                        <div class="letter-dx-field">
                            <select id="letter-dx" class="form-select" multiple data-icd-url="{{ route('care.diagnosis.search', $visit) }}">
                                @foreach ($dxItems as $item)
                                    <option value="{{ $item['key'] }}" selected>{{ $item['text'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div id="letter-dx-fields">
                            @forelse ($dxItems as $index => $item)
                                @if ($item['id'] > 0)
                                    <input type="hidden" name="diagnoses[{{ $index }}][id]" value="{{ $item['id'] }}">
                                @endif
                                <input type="hidden" name="diagnoses[{{ $index }}][selected]" value="1">
                                <input type="hidden" name="diagnoses[{{ $index }}][kind]" value="working">
                                <input type="hidden" name="diagnoses[{{ $index }}][code]" value="{{ $item['code'] }}">
                                <input type="hidden" name="diagnoses[{{ $index }}][description]" value="{{ $item['description'] }}">
                            @empty
                                <input type="hidden" name="diagnoses[0][selected]" value="0">
                            @endforelse
                        </div>
                        <script type="application/json" id="letter-dx-seed">@json($dxItems)</script>
                    </div>

                    <div class="mt-3">
                        <label class="form-label" for="letter-body">Isi keterangan</label>
                        <textarea name="body" id="letter-body" rows="8" class="form-control">{{ old('body', $defaults['body']) }}</textarea>
                        <p class="text-muted small mb-0">Teks ini yang tercetak. Mengganti jenis surat mengisi ulang teks selama belum Anda ubah.</p>
                    </div>

                    <div class="mt-3 d-flex gap-2">
                        <x-button type="submit" icon="bx bx-check">Terbitkan surat</x-button>
                        <x-button href="{{ route('care.show', ['visit' => $visit, 'tab' => 'ringkasan']) }}" variant="light">Batal</x-button>
                    </div>
                </x-card>
            </div>
            <div class="col-lg-6">
                <div class="letter-preview-wrap">
                    <x-card title="Pratinjau">
                        <iframe id="letter-preview-frame" class="letter-preview-frame" title="Pratinjau surat"></iframe>
                    </x-card>
                </div>
            </div>
        </div>
    </form>
    <script type="application/json" id="letter-suggestions">@json($suggestions)</script>
    <script type="application/json" id="letter-preview-initial">@json($previewHtml ?? '')</script>
@endsection

@push('styles')
<link href="{{ theme('libs/select2/css/select2.min.css') }}" rel="stylesheet">
<style>
    .letter-preview-frame {
        display: block;
        width: 100%;
        height: calc(100vh - 8.5rem);
        min-height: 680px;
        border: 1px solid var(--kd-border);
        background: var(--kd-surface);
    }
    .letter-dx-field .select2-container {
        width: 100% !important;
        display: block;
    }
    .letter-dx-field .select2-container .select2-selection--multiple {
        min-height: 38px;
        border: 1px solid var(--kd-border-strong);
        border-radius: 0.25rem;
        background: var(--kd-surface);
    }
    .letter-dx-field .select2-container .select2-selection--multiple .select2-selection__rendered {
        padding: 0.2rem 0.35rem;
    }
    .letter-dx-field .select2-container .select2-search--inline .select2-search__field {
        margin-top: 0.35rem;
        color: var(--kd-ink);
    }
    .letter-dx-field .select2-container .select2-selection__choice {
        background: var(--kd-bg);
        border: 1px solid var(--kd-border);
        color: var(--kd-navy);
        border-radius: 0.25rem;
        padding: 0.1rem 0.35rem;
    }
</style>
@endpush

@push('scripts')
<script src="{{ theme('libs/select2/js/select2.min.js') }}"></script>
<script>
(function () {
    var form = document.getElementById('letter-form');
    var type = document.getElementById('letter-type');
    var body = document.getElementById('letter-body');
    var suggestions = {};
    var frame = document.getElementById('letter-preview-frame');
    var frameUrl = @json(route('care.letters.frame', $visit));
    var token = document.querySelector('meta[name="csrf-token"]');
    var timer = null;
    try { suggestions = JSON.parse(document.getElementById('letter-suggestions').textContent || '{}'); } catch (e) {}
    try {
        var initial = JSON.parse(document.getElementById('letter-preview-initial').textContent || '""');
        if (frame && initial) frame.srcdoc = initial;
    } catch (e) {}
    var dirty = false;

    function refreshFrame() {
        if (!form || !frame) return;
        var payload = new FormData(form);
        fetch(frameUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                'Accept': 'text/html',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: payload
        }).then(function (res) {
            if (!res.ok) throw new Error();
            return res.text();
        }).then(function (html) {
            frame.srcdoc = html;
        }).catch(function () {});
    }

    var dxSelect = document.getElementById('letter-dx');
    var dxFields = document.getElementById('letter-dx-fields');
    var dxKnown = {};
    try {
        JSON.parse(document.getElementById('letter-dx-seed').textContent || '[]').forEach(function (item) {
            dxKnown[item.key] = item;
        });
    } catch (e) {}

    function writeDxFields() {
        if (!dxFields || !dxSelect || !window.jQuery) return;
        var values = jQuery(dxSelect).val() || [];
        dxFields.innerHTML = '';
        if (!values.length) {
            dxFields.innerHTML = '<input type="hidden" name="diagnoses[0][selected]" value="0">';
            return;
        }
        values.forEach(function (key, index) {
            var item = dxKnown[key] || { id: 0, code: key, description: '' };
            var prefix = 'diagnoses[' + index + ']';
            if (item.id) dxFields.insertAdjacentHTML('beforeend', '<input type="hidden" name="' + prefix + '[id]" value="' + item.id + '">');
            dxFields.insertAdjacentHTML('beforeend', '<input type="hidden" name="' + prefix + '[selected]" value="1">');
            dxFields.insertAdjacentHTML('beforeend', '<input type="hidden" name="' + prefix + '[kind]" value="working">');
            dxFields.insertAdjacentHTML('beforeend', '<input type="hidden" name="' + prefix + '[code]" value="">');
            dxFields.insertAdjacentHTML('beforeend', '<input type="hidden" name="' + prefix + '[description]" value="">');
            dxFields.querySelector('[name="' + prefix + '[code]"]').value = item.code || '';
            dxFields.querySelector('[name="' + prefix + '[description]"]').value = item.description || '';
        });
    }

    function initDxSelect() {
        if (!dxSelect || !window.jQuery || !jQuery.fn || !jQuery.fn.select2) return;
        var $dx = jQuery(dxSelect);
        $dx.select2({
            width: '100%',
            placeholder: 'Ketik kode atau nama ICD-10',
            minimumInputLength: 1,
            dropdownParent: jQuery(document.body),
            ajax: {
                url: dxSelect.getAttribute('data-icd-url'),
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term || '', page: params.page || 1 };
                },
                processResults: function (data, params) {
                    params.page = params.page || 1;
                    return {
                        results: data.results || [],
                        pagination: { more: !!(data.pagination && data.pagination.more) }
                    };
                },
                cache: true
            },
            language: {
                noResults: function () { return 'ICD-10 tidak ditemukan'; },
                searching: function () { return 'Mencari…'; },
                inputTooShort: function () { return 'Ketik kode atau nama ICD-10'; },
                loadingMore: function () { return 'Memuat lainnya…'; }
            }
        });
        $dx.on('select2:select', function (e) {
            var data = e.params.data || {};
            var key = data.code || data.id || '';
            dxKnown[key] = {
                key: key,
                id: dxKnown[key] ? dxKnown[key].id : 0,
                code: data.code || data.id || '',
                description: data.description || ''
            };
        });
        $dx.on('change', function () {
            writeDxFields();
            render();
        });
    }

    function render() {
        clearTimeout(timer);
        timer = setTimeout(refreshFrame, 300);
    }

    if (body) {
        body.addEventListener('input', function () { dirty = true; });
    }

    function showPanels() {
        var current = type ? type.value : '';
        document.querySelectorAll('[data-letter-panel]').forEach(function (panel) {
            var keys = panel.getAttribute('data-letter-panel').split(',');
            panel.hidden = keys.indexOf(current) === -1;
        });
        if (!dirty && body && suggestions[current]) {
            body.value = suggestions[current];
        }
        render();
    }

    initDxSelect();
    if (type) type.addEventListener('change', showPanels);
    if (form) form.addEventListener('input', render);
    if (form) form.addEventListener('change', render);
    showPanels();
})();
</script>
@endpush
