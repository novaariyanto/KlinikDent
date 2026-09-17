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
    <div class="col-md-6">
        <x-input name="price" type="number" label="Harga" :value="$tariff?->price" required />
    </div>
    <div class="col-md-6">
        <x-input name="effective_date" type="date" label="Tanggal Berlaku" :value="old('effective_date', optional($tariff?->effective_date)->format('Y-m-d') ?? now()->toDateString())" required />
    </div>
</div>
