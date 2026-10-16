<div class="mb-4">
    <h3 class="mb-1">Setup farmasi</h3>
    <p class="text-muted">Opsional. Jika dilewati, tidak ada data obat yang dibuat otomatis.</p>
</div>

<form method="POST" action="{{ route('onboarding.step', 'medicines') }}">
    @csrf
    <x-card>
        @php $mode = old('mode', 'skip'); @endphp
        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="mode" id="med-skip" value="skip" @checked($mode === 'skip')>
            <label class="form-check-label" for="med-skip">Lewati dulu</label>
        </div>
        <div class="form-check mb-2">
            <input class="form-check-input" type="radio" name="mode" id="med-template" value="template" @checked($mode === 'template')>
            <label class="form-check-label" for="med-template">Gunakan template obat</label>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="radio" name="mode" id="med-manual" value="manual" @checked($mode === 'manual')>
            <label class="form-check-label" for="med-manual">Tambahkan obat</label>
        </div>

        <div id="manual-medicines" class="{{ $mode === 'manual' ? '' : 'd-none' }}">
            <div id="medicine-list">
                <div class="row g-2 medicine-row mb-2">
                    <div class="col-md-3"><x-input name="medicines[0][name]" label="Nama obat" /></div>
                    <div class="col-md-2">
                        <x-input name="medicines[0][kfa_code]" label="Kode KFA" placeholder="Opsional" />
                    </div>
                    <div class="col-md-1">
                        <x-input name="medicines[0][strength_value]" type="number" step="0.0001" label="Kekuatan" placeholder="500" />
                    </div>
                    <div class="col-md-1">
                        <x-input name="medicines[0][strength_unit]" label="Unit" placeholder="mg" />
                    </div>
                    <div class="col-md-2">
                        <x-select name="medicines[0][unit]" label="Satuan" :options="\App\Models\Medicine::UNITS" />
                    </div>
                    <div class="col-md-2">
                        <x-select name="medicines[0][category]" label="Kategori" :options="\App\Models\Medicine::CATEGORIES" />
                    </div>
                    <div class="col-md-1">
                        <x-input name="medicines[0][base_price]" type="number" label="Harga" value="0" />
                    </div>
                </div>
                <p class="text-muted small">KFA wajib nanti untuk sync resep ke SATUSEHAT. Kekuatan dipakai otomatis pada racikan d.t.d (SD).</p>
            </div>
        </div>

        @include('onboarding.partials.actions', ['canSkip' => true, 'step' => 'medicines'])
    </x-card>
</form>

@push('scripts')
<script>
document.querySelectorAll('input[name="mode"]').forEach((el) => {
    el.addEventListener('change', () => {
        document.getElementById('manual-medicines')?.classList.toggle('d-none', el.value !== 'manual' && !document.getElementById('med-manual').checked);
        if (document.getElementById('med-manual').checked) {
            document.getElementById('manual-medicines')?.classList.remove('d-none');
        }
    });
});
</script>
@endpush
