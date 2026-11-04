@php
    $tariff = $tariff ?? null;
    $procedureOptions = collect($procedures ?? [])->mapWithKeys(fn ($procedure) => [$procedure->id => $procedure->code.' — '.$procedure->name])->all();
    $branchOptions = collect($branches ?? [])->mapWithKeys(fn ($branch) => [$branch->id => $branch->name])->all();
    $payerOptions = collect($payers ?? [])->mapWithKeys(fn ($payer) => [$payer->id => $payer->name])->all();
@endphp

<div class="row">
    <div class="col-md-12">
        <x-select name="procedure_id" label="Tindakan" :options="$procedureOptions" :selected="old('procedure_id', $tariff?->procedure_id)" required />
    </div>
    <div class="col-md-6">
        <x-select name="branch_id" label="Cabang" :options="$branchOptions" :selected="old('branch_id', $tariff?->branch_id)" placeholder="Semua cabang" />
    </div>
    <div class="col-md-6">
        <x-select name="payer_id" label="Penjamin" :options="$payerOptions" :selected="old('payer_id', $tariff?->payer_id)" placeholder="Default / semua penjamin" />
    </div>
    <div class="col-md-4">
        <x-input name="doctor_fee" id="tariff-doctor-fee" type="number" step="0.01" min="0" label="Jasa dokter" :value="old('doctor_fee', $tariff?->doctor_fee)" />
    </div>
    <div class="col-md-4">
        <x-input name="bhp_amount" id="tariff-bhp" type="number" step="0.01" min="0" label="BHP" :value="old('bhp_amount', $tariff?->bhp_amount)" />
    </div>
    <div class="col-md-4">
        <x-input name="clinic_fee" id="tariff-clinic" type="number" step="0.01" min="0" label="Jasa klinik" :value="old('clinic_fee', $tariff?->clinic_fee)" />
    </div>
    <div class="col-md-6">
        <x-input name="price" id="tariff-price" type="number" step="0.01" min="0" label="Tarif normal (total)" :value="old('price', $tariff?->price)" required />
        <div class="form-text">Jika komponen diisi, total otomatis = jasa dokter + BHP + jasa klinik.</div>
    </div>
    <div class="col-md-6">
        <x-input name="effective_date" type="date" label="Tanggal Berlaku" :value="old('effective_date', optional($tariff?->effective_date)->format('Y-m-d') ?? now()->toDateString())" required />
    </div>
</div>

<script>
(function () {
    var doctor = document.getElementById('tariff-doctor-fee');
    var bhp = document.getElementById('tariff-bhp');
    var clinic = document.getElementById('tariff-clinic');
    var price = document.getElementById('tariff-price');
    if (!doctor || !bhp || !clinic || !price) return;
    function sync() {
        var total = (Number(doctor.value || 0) + Number(bhp.value || 0) + Number(clinic.value || 0));
        if (total > 0) {
            price.value = total.toFixed(2);
            price.readOnly = true;
        } else {
            price.readOnly = false;
        }
    }
    [doctor, bhp, clinic].forEach(function (el) {
        el.addEventListener('input', sync);
    });
    sync();
})();
</script>
