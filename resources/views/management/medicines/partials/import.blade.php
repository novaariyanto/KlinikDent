<x-card title="Import Data Obat (XLSX)">
    <p class="text-muted mb-3">
        Unggah file Excel (.xlsx). Sistem membaca file dulu, lalu menyimpan obat satu per satu via AJAX.
    </p>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <x-button href="{{ route('management.medicines.import.template') }}" variant="secondary" icon="bx bx-download">
            Unduh Template XLSX
        </x-button>
    </div>

    <div class="mb-3">
        <label class="form-label" for="medicine-import-file">File XLSX</label>
        <input
            id="medicine-import-file"
            type="file"
            class="form-control"
            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        >
        <div class="form-text">
            Kolom: name, kfa_code, strength_value, strength_unit, unit, category, base_price, margin_type, margin_value, is_active.
            Nama yang sudah ada akan dilewati.
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" class="btn btn-primary" id="medicine-import-start" disabled>
            <i class="bx bx-upload me-1"></i>Mulai Import
        </button>
        <button type="button" class="btn btn-light" id="medicine-import-cancel" hidden>Batalkan</button>
    </div>

    <div class="progress mb-2" style="height: 0.65rem;" hidden id="medicine-import-progress-wrap">
        <div class="progress-bar" id="medicine-import-progress" role="progressbar" style="width: 0%"></div>
    </div>
    <div class="small mb-2" id="medicine-import-status" hidden></div>
    <div class="table-responsive" id="medicine-import-log-wrap" hidden>
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>Baris</th>
                    <th>Nama</th>
                    <th>Status</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody id="medicine-import-log"></tbody>
        </table>
    </div>

    <div class="mt-3 small text-muted">
        <div><strong>unit:</strong> {{ implode(', ', array_keys(\App\Models\Medicine::UNITS)) }}</div>
        <div><strong>category:</strong> {{ implode(', ', array_keys(\App\Models\Medicine::CATEGORIES)) }}</div>
        <div><strong>margin_type:</strong> {{ implode(', ', array_keys(\App\Models\Medicine::MARGIN_TYPES)) }}</div>
        <div><strong>is_active:</strong> 1 / 0</div>
    </div>
</x-card>

<script>
(function () {
    var fileInput = document.getElementById('medicine-import-file');
    var startBtn = document.getElementById('medicine-import-start');
    var cancelBtn = document.getElementById('medicine-import-cancel');
    var progressWrap = document.getElementById('medicine-import-progress-wrap');
    var progressBar = document.getElementById('medicine-import-progress');
    var statusEl = document.getElementById('medicine-import-status');
    var logWrap = document.getElementById('medicine-import-log-wrap');
    var logBody = document.getElementById('medicine-import-log');
    if (!fileInput || !startBtn) return;

    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    var parseUrl = @json(route('management.medicines.import.parse'));
    var rowUrl = @json(route('management.medicines.import.row'));
    var cancelled = false;
    var running = false;

    function setProgress(done, total) {
        var pct = total > 0 ? Math.round((done / total) * 100) : 0;
        progressWrap.hidden = false;
        progressBar.style.width = pct + '%';
        progressBar.textContent = pct + '%';
        statusEl.hidden = false;
        statusEl.textContent = 'Proses ' + done + ' / ' + total;
    }

    function appendLog(row, name, status, message) {
        logWrap.hidden = false;
        var tr = document.createElement('tr');
        var badge = status === 'created'
            ? 'bg-success'
            : (status === 'skipped' ? 'bg-warning text-dark' : 'bg-danger');
        tr.innerHTML = '<td>' + (row || '-') + '</td>'
            + '<td>' + (name || '-') + '</td>'
            + '<td><span class="badge ' + badge + '">' + status + '</span></td>'
            + '<td>' + (message || '') + '</td>';
        logBody.prepend(tr);
    }

    fileInput.addEventListener('change', function () {
        startBtn.disabled = !(fileInput.files && fileInput.files[0]) || running;
    });

    cancelBtn.addEventListener('click', function () {
        cancelled = true;
    });

    startBtn.addEventListener('click', async function () {
        var file = fileInput.files && fileInput.files[0];
        if (!file || running) return;

        cancelled = false;
        running = true;
        startBtn.disabled = true;
        cancelBtn.hidden = false;
        logBody.innerHTML = '';
        logWrap.hidden = true;
        statusEl.hidden = false;
        statusEl.textContent = 'Membaca file XLSX...';
        setProgress(0, 1);

        try {
            var formData = new FormData();
            formData.append('file', file);

            var parseRes = await fetch(parseUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf
                },
                body: formData
            });
            var parseJson = await parseRes.json().catch(function () { return {}; });
            if (!parseRes.ok) {
                var parseError = parseJson.message
                    || (parseJson.errors && parseJson.errors.file && parseJson.errors.file[0])
                    || 'Gagal membaca file XLSX.';
                throw new Error(parseError);
            }

            var rows = parseJson.rows || [];
            var total = rows.length;
            var created = 0;
            var skipped = 0;
            var failed = 0;

            setProgress(0, total);

            for (var i = 0; i < rows.length; i++) {
                if (cancelled) break;
                var item = rows[i];
                var payload = Object.assign({}, item.data || {}, { row: item.row });

                try {
                    var rowRes = await fetch(rowUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: JSON.stringify(payload)
                    });
                    var rowJson = await rowRes.json().catch(function () { return {}; });
                    var status = rowJson.status || (rowRes.ok ? 'created' : 'failed');
                    var message = rowJson.message
                        || (rowJson.errors ? Object.values(rowJson.errors)[0][0] : null)
                        || (rowRes.ok ? 'OK' : 'Gagal menyimpan.');

                    if (status === 'created') created++;
                    else if (status === 'skipped') skipped++;
                    else failed++;

                    appendLog(item.row, payload.name, status, message);
                } catch (err) {
                    failed++;
                    appendLog(item.row, payload.name, 'failed', err.message || 'Gagal jaringan.');
                }

                setProgress(i + 1, total);
            }

            statusEl.textContent = (cancelled ? 'Dibatalkan. ' : 'Selesai. ')
                + 'Ditambah: ' + created
                + ', dilewati: ' + skipped
                + ', gagal: ' + failed
                + '.';
        } catch (error) {
            statusEl.hidden = false;
            statusEl.textContent = error.message || 'Import gagal.';
            progressWrap.hidden = true;
        } finally {
            running = false;
            cancelBtn.hidden = true;
            startBtn.disabled = !(fileInput.files && fileInput.files[0]);
        }
    });
})();
</script>
