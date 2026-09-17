@php $payer = $payer ?? null; @endphp

<div class="row">
    <div class="col-md-6">
        <x-select name="type" label="Tipe" :options="\App\Enums\PayerType::options()" :selected="old('type', $payer?->type?->value)" required />
    </div>
    <div class="col-md-6">
        <x-input name="name" label="Nama Penjamin" :value="$payer?->name" required />
    </div>
    <div class="col-md-6">
        <x-input name="contract_number" label="No. Kontrak" :value="$payer?->contract_number" />
    </div>
</div>
