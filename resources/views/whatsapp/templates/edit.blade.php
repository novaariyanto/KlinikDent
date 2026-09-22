@extends('layouts.app')

@section('title', 'Edit Template')

@section('content')
    <x-page-header :title="$template->globalTemplate?->label ?? $template->event_key->label()" :breadcrumb="['WhatsApp Otomatis' => route('whatsapp.dashboard'), 'Template Pesan' => route('whatsapp.templates.index'), 'Edit' => null]" />
    <x-alert />
    @include('whatsapp.partials.nav')

    <form method="POST" action="{{ route('whatsapp.templates.update', $template) }}" id="whatsapp-template-form">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-xl-5">
                <x-card title="Konfigurasi klinik">
                    <p class="text-muted small">Event internal: <code>{{ $template->event_key->value }}</code>. Template global tidak diubah.</p>

                    @if ($template->remote_status)
                        <p class="mb-2">
                            Status Meta:
                            @if ($template->remote_status === 'approved')
                                <span class="badge bg-success">approved</span>
                            @elseif ($template->remote_status === 'pending')
                                <span class="badge bg-warning">pending</span>
                            @else
                                <span class="badge bg-danger">{{ $template->remote_status }}</span>
                            @endif
                            @if ($template->remoteDisplayName())
                                <span class="text-muted small">{{ $template->remoteDisplayName() }}</span>
                            @endif
                        </p>
                    @endif

                    @if ($remoteError)
                        <div class="alert alert-warning py-2">Daftar WhatsCRM tidak bisa diambil: {{ $remoteError }}. Nama template tetap bisa diisi manual.</div>
                    @endif

                    @php
                        $remoteOptions = collect($remoteTemplates)
                            ->mapWithKeys(fn ($item) => [
                                (string) ($item['name_template'] ?? $item['name'] ?? '') => ($item['display_name'] ?? $item['name_template'] ?? '').' ('.($item['name_template'] ?? $item['name'] ?? '').')',
                            ])
                            ->filter()
                            ->all();
                    @endphp

                    <x-select
                        name="remote_pick"
                        label="Pilih template approved WhatsCRM"
                        :options="$remoteOptions"
                        :selected="old('whatsapp_template_name', $template->whatsapp_template_name)"
                        placeholder="Pilih name_template WhatsCRM"
                        :disabled="! $canManage"
                    />
                    <p class="text-muted small mt-n2 mb-3">Kode event <code>{{ $template->event_key->value }}</code> bukan nama template Meta. Pilih dari daftar, lalu Ambil dari WhatsCRM.</p>

                    <x-input name="whatsapp_template_name" label="Nama Template WhatsApp" :value="old('whatsapp_template_name', $template->whatsapp_template_name)" required :disabled="! $canManage" />
                    <x-select
                        name="language_code"
                        label="Bahasa"
                        :options="$languages"
                        :selected="old('language_code', $template->language_code)"
                        :placeholder="null"
                        required
                        :disabled="! $canManage"
                    />
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="is_enabled" @checked(old('is_enabled', $template->is_enabled)) @disabled(! $canManage || ! $template->isRemoteApproved())>
                        <label class="form-check-label" for="is_enabled">Aktif</label>
                    </div>
                    @if (! $template->isRemoteApproved())
                        <p class="text-warning small">Template pending/rejected tidak bisa diaktifkan untuk pengiriman.</p>
                    @endif
                    <div class="mb-3">
                        <label for="preview_body" class="form-label">Isi / Preview Template</label>
                        <textarea name="preview_body" id="preview_body" rows="12" class="form-control font-monospace @error('preview_body') is-invalid @enderror" @disabled(! $canManage)>{{ old('preview_body', $template->preview_body) }}</textarea>
                        @error('preview_body')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <p class="text-muted small mt-1 mb-0">Gunakan <code>@{{1}}</code>, <code>@{{2}}</code>, dan seterusnya. Jumlah parameter mengikuti isi preview.</p>
                    </div>
                    @if ($canManage)
                        <div class="d-flex flex-wrap gap-2 mb-2">
                            <x-button type="submit" icon="bx bx-save">Simpan template</x-button>
                            <button type="button" class="btn btn-outline-primary" id="sync-btn">Ambil dari WhatsCRM</button>
                            <button type="submit" form="status-form" class="btn btn-secondary">Cek status Meta</button>
                            <x-button href="{{ route('whatsapp.templates.create', $template) }}" variant="secondary">Ajukan ke Meta</x-button>
                        </div>
                    @endif
                </x-card>
            </div>
            <div class="col-xl-7">
                <x-card title="Mapping Parameter">
                    @error('parameters')<div class="alert alert-danger">{{ $message }}</div>@enderror
                    <x-table>
                        <thead>
                            <tr>
                                <th width="80">Param</th>
                                <th>Label</th>
                                <th>Sumber data</th>
                            </tr>
                        </thead>
                        <tbody id="parameter-rows"></tbody>
                    </x-table>
                    <p class="text-muted small">Sumber diambil dari data operasional KlinikDent, bukan dari cabang aktif user.</p>
                </x-card>
            </div>
        </div>
    </form>

    @if ($canManage)
        <x-card title="Tes kirim template" class="mt-3">
            <p class="text-muted small">Kirim template tersimpan ke nomor Anda. Simpan dulu jika nama, bahasa, atau preview baru diubah. Tes tidak memakai data pasien otomatis.</p>

            @if (! $settingReady)
                <div class="alert alert-warning py-2">WhatsApp belum terhubung. Isi API key di pengaturan dulu.</div>
            @elseif (! $template->isRemoteApproved())
                <div class="alert alert-warning py-2">Template pending/rejected tidak bisa dikirim. Cek status Meta sampai approved.</div>
            @endif

            <form method="POST" action="{{ route('whatsapp.templates.test', $template) }}" id="test-send-form">
                @csrf
                <div class="row">
                    <div class="col-md-4">
                        <x-input name="phone_number" label="Nomor WhatsApp" :value="$testPhone" required :disabled="! $settingReady || ! $template->isRemoteApproved()" />
                    </div>
                    <div class="col-md-4">
                        <x-input name="to_name" label="Nama penerima" :value="old('to_name', auth()->user()?->name)" :disabled="! $settingReady || ! $template->isRemoteApproved()" />
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nilai parameter tes</label>
                    <div id="test-parameter-rows" class="row g-2"></div>
                    <p class="text-muted small mt-1 mb-0">Urutan sama dengan placeholder WhatsCRM. Isi contoh nilai, bukan <code>@{{1}}</code>.</p>
                </div>
                <x-button type="submit" icon="bx bx-send" :disabled="! $settingReady || ! $template->isRemoteApproved()">Kirim tes</x-button>
            </form>
        </x-card>
    @endif

    @if ($canManage)
        <form method="POST" action="{{ route('whatsapp.templates.sync', $template) }}" id="sync-form" class="d-none">
            @csrf
            <input type="hidden" name="name" id="sync-name" value="{{ $template->whatsapp_template_name }}">
        </form>
        <form method="POST" action="{{ route('whatsapp.templates.status', $template) }}" id="status-form" class="d-none">
            @csrf
        </form>
    @endif
@endsection

@push('scripts')
<script>
    (function () {
        const sources = @json($sources);
        const existing = @json(old('parameters', $mapping));
        const preview = document.getElementById('preview_body');
        const rows = document.getElementById('parameter-rows');
        const canManage = @json($canManage);
        const nameInput = document.getElementById('whatsapp_template_name');
        const pick = document.getElementById('remote_pick');
        const language = document.getElementById('language_code');
        const testRows = document.getElementById('test-parameter-rows');
        const oldTestParams = @json(old('body_params', []));
        const canTest = @json($canManage && ($settingReady ?? false) && $template->isRemoteApproved());
        const detailUrl = @json(route('whatsapp.templates.remote.show', ['name' => '__NAME__']));

        function byPosition(list) {
            const map = {};
            (list || []).forEach(function (row) {
                map[String(row.position)] = row;
            });
            return map;
        }

        function positionsFrom(body) {
            const matches = String(body || '').matchAll(/\{\{(\d+)\}\}/g);
            const seen = new Set();
            const positions = [];
            for (const match of matches) {
                const position = Number(match[1]);
                if (! seen.has(position)) {
                    seen.add(position);
                    positions.push(position);
                }
            }
            positions.sort(function (a, b) { return a - b; });
            return positions;
        }

        function optionList(selected) {
            return Object.keys(sources).map(function (key) {
                const isSelected = key === selected ? ' selected' : '';
                return '<option value="' + key + '"' + isSelected + '>' + sources[key] + ' (' + key + ')</option>';
            }).join('');
        }

        function escapeHtml(value) {
            return String(value || '').replace(/[&<>"']/g, function (ch) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
            });
        }

        function renderTestParams(current) {
            if (! testRows) {
                return;
            }

            const positions = positionsFrom(preview.value);
            testRows.innerHTML = '';

            if (positions.length === 0) {
                testRows.innerHTML = '<div class="col-12 text-muted small">Template ini tidak punya parameter.</div>';
                return;
            }

            positions.forEach(function (position) {
                const row = current[String(position)] || {};
                const value = oldTestParams[position] || oldTestParams[String(position)] || row.label || '';
                const wrap = document.createElement('div');
                wrap.className = 'col-md-4';
                wrap.innerHTML =
                    '<label class="form-label small mb-1"><code>@{{' + position + '}}</code> ' + escapeHtml(row.label || '') + '</label>' +
                    '<input class="form-control" name="body_params[' + position + ']" value="' + escapeHtml(value) + '" ' + (canTest ? 'required' : 'disabled') + '>';
                testRows.appendChild(wrap);
            });
        }

        function render() {
            const current = byPosition(existing.concat(Array.from(rows.querySelectorAll('[data-row]')).map(function (row) {
                return {
                    position: row.getAttribute('data-position'),
                    label: row.querySelector('[data-label]')?.value,
                    source: row.querySelector('[data-source]')?.value,
                };
            })));
            const positions = positionsFrom(preview.value);
            rows.innerHTML = '';

            if (positions.length === 0) {
                rows.innerHTML = '<tr><td colspan="3" class="text-muted">Tidak ada parameter. Tambahkan @{{1}} pada preview.</td></tr>';
                renderTestParams(current);
                return;
            }

            positions.forEach(function (position) {
                const row = current[String(position)] || {};
                const tr = document.createElement('tr');
                tr.setAttribute('data-row', '1');
                tr.setAttribute('data-position', String(position));
                tr.innerHTML =
                    '<td><input type="hidden" name="parameters[' + position + '][position]" value="' + position + '"><code>@{{' + position + '}}</code></td>' +
                    '<td><input class="form-control" data-label name="parameters[' + position + '][label]" value="' + escapeHtml(row.label || '') + '" ' + (canManage ? 'required' : 'disabled') + '></td>' +
                    '<td><select class="form-select" data-source name="parameters[' + position + '][source]" ' + (canManage ? 'required' : 'disabled') + '>' +
                    '<option value="">Pilih sumber</option>' + optionList(row.source || '') +
                    '</select></td>';
                rows.appendChild(tr);
            });

            renderTestParams(current);
        }

        function applyRemote(data) {
            if (! data) {
                return;
            }
            if (data.name_template && nameInput) {
                nameInput.value = data.name_template;
            }
            if (data.language && language) {
                language.value = data.language === 'en_US' ? 'en' : data.language;
            }
            if (data.preview) {
                preview.value = data.preview;
                render();
            }
        }

        if (pick) {
            pick.addEventListener('change', function () {
                if (pick.value && nameInput) {
                    nameInput.value = pick.value;
                }
            });
        }

        const syncBtn = document.getElementById('sync-btn');
        if (syncBtn) {
            syncBtn.addEventListener('click', function () {
                const name = (pick && pick.value) ? pick.value : (nameInput?.value || '');
                if (! name) {
                    alert('Pilih template approved WhatsCRM, atau isi nama template WhatsCRM.');
                    return;
                }
                const syncName = document.getElementById('sync-name');
                if (syncName) {
                    syncName.value = name;
                }
                fetch(detailUrl.replace('__NAME__', encodeURIComponent(name)), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then(function (response) { return response.json().then(function (json) { return { ok: response.ok, json: json }; }); })
                    .then(function (result) {
                        if (! result.json?.success) {
                            throw new Error(result.json?.message || 'Gagal mengambil template');
                        }
                        applyRemote(result.json.data);
                    })
                    .catch(function (error) {
                        alert(error.message);
                    });
            });
        }

        preview.addEventListener('input', render);
        render();
    })();
</script>
@endpush
