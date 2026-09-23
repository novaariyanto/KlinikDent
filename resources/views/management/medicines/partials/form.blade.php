@php $medicine = $medicine ?? null; @endphp

<div class="row">
    <div class="col-md-6">
        <x-input name="name" label="Nama Obat" :value="$medicine?->name" required />
    </div>
    <div class="col-md-3">
        <x-select name="unit" label="Satuan" :options="\App\Models\Medicine::UNITS" :selected="old('unit', $medicine?->unit)" required />
    </div>
    <div class="col-md-3">
        <x-select name="category" label="Kategori" :options="\App\Models\Medicine::CATEGORIES" :selected="old('category', $medicine?->category)" required />
    </div>
    <div class="col-md-4">
        <x-input name="base_price" type="number" label="Harga Dasar" :value="$medicine?->base_price" step="0.01" min="0" required />
    </div>
    <div class="col-md-4">
        <x-select
            name="margin_type"
            label="Tipe Margin"
            :options="\App\Models\Medicine::MARGIN_TYPES"
            :selected="old('margin_type', $medicine?->margin_type ?? 'fixed')"
            placeholder=""
            required
        />
    </div>
    <div class="col-md-4">
        <x-input name="margin_value" type="number" label="Nilai Margin" :value="$medicine?->margin_value ?? 0" step="0.01" min="0" required />
    </div>
    <div class="col-md-4">
        <label class="form-label" for="sell_price_preview">Harga Jual</label>
        <input id="sell_price_preview" type="text" class="form-control" readonly>
        <div class="form-text" id="margin_hint">Harga jual dihitung dari harga dasar ditambah margin.</div>
    </div>
    <div class="col-md-6">
        <div class="form-check form-switch mb-3 mt-4">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $medicine?->is_active ?? true))>
            <label class="form-check-label" for="is_active">Aktif</label>
        </div>
    </div>
</div>

<script>
    (function () {
        const base = document.getElementById('base_price');
        const type = document.getElementById('margin_type');
        const margin = document.getElementById('margin_value');
        const preview = document.getElementById('sell_price_preview');
        const hint = document.getElementById('margin_hint');
        if (!base || !type || !margin || !preview) return;

        const format = (value) => new Intl.NumberFormat('id-ID').format(Math.round(value));

        const refresh = () => {
            const baseValue = Number(base.value || 0);
            const marginValue = Number(margin.value || 0);
            const percent = type.value === 'percent';
            const sell = percent ? baseValue + (baseValue * marginValue / 100) : baseValue + marginValue;
            preview.value = 'Rp ' + format(Number.isFinite(sell) ? sell : 0);
            if (hint) {
                hint.textContent = percent
                    ? 'Margin persen dihitung dari harga dasar. Contoh 10 berarti tambah 10%.'
                    : 'Margin harga tetap ditambahkan langsung ke harga dasar (rupiah).';
            }
        };

        [base, type, margin].forEach((el) => el.addEventListener('input', refresh));
        type.addEventListener('change', refresh);
        refresh();
    })();
</script>
