<x-card title="Impor artikel (XLSX)" class="mb-3">
    <p class="text-muted mb-3">
        Unggah berkas Excel artikel, atau impor langsung 29 artikel SEO yang sudah ada di server.
        Slug yang sudah tersimpan dilewati. Canonical <span class="font-monospace">/artikel/{slug}</span> tidak dipakai; URL blog yang berlaku.
    </p>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <x-button href="{{ asset('klinikdent_29_artikel_seo.xlsx') }}" variant="secondary" icon="bx bx-download">
            Unduh berkas SEO
        </x-button>
        <button type="button" class="btn btn-primary" id="cms-import-bundled">
            <i class="bx bx-file me-1"></i>Impor berkas bawaan
        </button>
    </div>

    <div class="mb-3">
        <label class="form-label" for="cms-import-file">File XLSX</label>
        <input
            id="cms-import-file"
            type="file"
            class="form-control"
            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        >
        <div class="form-text">
            Kolom: Judul, Slug, Ringkasan, Isi artikel (HTML), SVG, FAQ JSON, Status, Kategori, Penulis, Reviewer, Tags, dan field SEO.
        </div>
    </div>

    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="cms-import-overwrite">
        <label class="form-check-label" for="cms-import-overwrite">Perbarui artikel yang slug-nya sudah ada (status mengikuti file)</label>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" class="btn btn-primary" id="cms-import-start" disabled>
            <i class="bx bx-upload me-1"></i>Impor file terpilih
        </button>
    </div>

    <div class="small mb-2" id="cms-import-status" hidden></div>
    <div class="table-responsive" id="cms-import-log-wrap" hidden>
        <table class="table table-sm mb-0">
            <thead>
                <tr>
                    <th>Baris</th>
                    <th>Judul</th>
                    <th>Status</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody id="cms-import-log"></tbody>
        </table>
    </div>
</x-card>

<script>
(function () {
    var fileInput = document.getElementById('cms-import-file');
    var startBtn = document.getElementById('cms-import-start');
    var bundledBtn = document.getElementById('cms-import-bundled');
    var overwriteEl = document.getElementById('cms-import-overwrite');
    var statusEl = document.getElementById('cms-import-status');
    var logWrap = document.getElementById('cms-import-log-wrap');
    var logBody = document.getElementById('cms-import-log');
    if (!startBtn || !bundledBtn) return;

    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    var importUrl = @json(route('cms.posts.import'));
    var running = false;

    var badgeClass = {
        created: 'bg-success',
        updated: 'bg-info',
        skipped: 'bg-warning text-dark',
        failed: 'bg-danger'
    };
    var badgeLabel = {
        created: 'Ditambah',
        updated: 'Diperbarui',
        skipped: 'Dilewati',
        failed: 'Gagal'
    };

    function setBusy(busy) {
        running = busy;
        startBtn.disabled = busy || !(fileInput.files && fileInput.files[0]);
        bundledBtn.disabled = busy;
        if (fileInput) fileInput.disabled = busy;
        if (overwriteEl) overwriteEl.disabled = busy;
    }

    function renderResults(payload) {
        statusEl.hidden = false;
        statusEl.textContent = payload.message || 'Impor selesai.';
        logBody.innerHTML = '';
        var rows = payload.results || [];
        logWrap.hidden = rows.length === 0;
        rows.forEach(function (item) {
            var tr = document.createElement('tr');
            var status = item.status || 'failed';
            var title = document.createElement('td');
            title.textContent = item.title || '—';
            tr.innerHTML = '<td>' + (item.row || '—') + '</td>';
            tr.appendChild(title);
            var statusCell = document.createElement('td');
            statusCell.innerHTML = '<span class="badge ' + (badgeClass[status] || 'bg-secondary') + '">' + (badgeLabel[status] || status) + '</span>';
            tr.appendChild(statusCell);
            var note = document.createElement('td');
            note.textContent = item.message || '';
            tr.appendChild(note);
            logBody.appendChild(tr);
        });
        document.dispatchEvent(new CustomEvent('cms-posts-imported'));
    }

    async function runImport(bundled) {
        if (running) return;
        var file = fileInput && fileInput.files ? fileInput.files[0] : null;
        if (!bundled && !file) return;
        if (overwriteEl && overwriteEl.checked && !window.confirm('Artikel dengan slug yang sama akan ditimpa, termasuk statusnya. Lanjutkan?')) {
            return;
        }

        setBusy(true);
        statusEl.hidden = false;
        statusEl.textContent = 'Mengimpor artikel...';
        logWrap.hidden = true;

        try {
            var formData = new FormData();
            formData.append('overwrite', overwriteEl && overwriteEl.checked ? '1' : '0');
            if (bundled) {
                formData.append('bundled', '1');
            } else {
                formData.append('file', file);
            }

            var res = await fetch(importUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf
                },
                body: formData
            });
            var json = await res.json().catch(function () { return {}; });
            if (!res.ok && !json.results) {
                var message = json.message
                    || (json.errors && json.errors.file && json.errors.file[0])
                    || 'Gagal mengimpor file.';
                throw new Error(message);
            }
            renderResults(json);
        } catch (error) {
            statusEl.hidden = false;
            statusEl.textContent = error.message || 'Impor gagal.';
        } finally {
            setBusy(false);
        }
    }

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            startBtn.disabled = running || !(fileInput.files && fileInput.files[0]);
        });
    }

    startBtn.addEventListener('click', function () { runImport(false); });
    bundledBtn.addEventListener('click', function () { runImport(true); });
})();
</script>
