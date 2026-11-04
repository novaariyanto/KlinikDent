@php
    $diagnosis = $diagnosis ?? null;
    $prefix = $prefix ?? 'dx';
    $kindValue = old('kind', $diagnosis?->kind?->value ?? 'clinical');
    $rankValue = old('rank', $diagnosis?->rank?->value ?? 'secondary');
@endphp
<div class="row g-2 mb-2">
    <div class="col-md-6">
        <label class="form-label" for="{{ $prefix }}-kind">Jenis diagnosis</label>
        <select id="{{ $prefix }}-kind" name="kind" class="form-select">
            @foreach (\App\Enums\DiagnosisKind::options() as $value => $label)
                <option value="{{ $value }}" @selected($kindValue === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="{{ $prefix }}-rank">Kedudukan</label>
        <select id="{{ $prefix }}-rank" name="rank" class="form-select">
            @foreach (\App\Enums\DiagnosisRank::options() as $value => $label)
                <option value="{{ $value }}" @selected($rankValue === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-12">
        <label class="form-label" for="{{ $prefix }}-note">Keterangan klinis</label>
        <textarea id="{{ $prefix }}-note" name="clinical_note" rows="2" class="form-control" maxlength="1000">{{ old('clinical_note', $diagnosis?->clinical_note) }}</textarea>
    </div>
    <div class="col-12">
        <label class="d-flex align-items-center gap-2">
            <input type="checkbox" data-dx-manual @checked($diagnosis && ! filled($diagnosis->code))>
            <span>Isi manual jika tidak ada di katalog</span>
        </label>
    </div>
    <div class="col-md-4" data-dx-manual-box @if (! ($diagnosis && ! filled($diagnosis->code))) hidden @endif>
        <label class="form-label" for="{{ $prefix }}-manual-code">Kode ICD-10</label>
        <input id="{{ $prefix }}-manual-code" class="form-control" data-dx-manual-code maxlength="50" value="{{ $diagnosis?->code }}" placeholder="Opsional">
    </div>
    <div class="col-md-8" data-dx-manual-box @if (! ($diagnosis && ! filled($diagnosis->code))) hidden @endif>
        <label class="form-label" for="{{ $prefix }}-manual-desc">Nama diagnosis</label>
        <input id="{{ $prefix }}-manual-desc" class="form-control" data-dx-manual-desc maxlength="255" value="{{ $diagnosis?->description }}" placeholder="Wajib jika manual">
    </div>
</div>
