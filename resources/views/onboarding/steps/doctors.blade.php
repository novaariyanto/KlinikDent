<div class="mb-4">
    <h3 class="mb-1">Dokter &amp; tenaga medis</h3>
    <p class="text-muted">Untuk klinik kecil, cukup mulai dengan 1 dokter. Langkah ini dapat dilewati.</p>
</div>

<form method="POST" action="{{ route('onboarding.step', 'doctors') }}">
    @csrf
    <div id="doctor-list">
        @php $rows = old('doctors', [['name' => '']]); @endphp
        @foreach ($rows as $index => $row)
            <div class="card mb-3 doctor-row">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Dokter</h5>
                        <button type="button" class="btn btn-sm btn-outline-danger js-remove-row">Hapus</button>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <x-input :name="'doctors['.$index.'][name]'" label="Nama" :value="$row['name'] ?? ''" />
                        </div>
                        <div class="col-md-3">
                            <x-input :name="'doctors['.$index.'][title]'" label="Gelar" :value="$row['title'] ?? ''" placeholder="drg." />
                        </div>
                        <div class="col-md-3">
                            <x-input :name="'doctors['.$index.'][sip]'" label="Nomor SIP" :value="$row['sip'] ?? ''" />
                        </div>
                        <div class="col-md-6">
                            <x-input :name="'doctors['.$index.'][specialization]'" label="Spesialisasi" :value="$row['specialization'] ?? ''" />
                        </div>
                        <div class="col-md-6">
                            <x-input :name="'doctors['.$index.'][phone]'" label="Nomor Telepon" :value="$row['phone'] ?? ''" />
                        </div>
                        <div class="col-md-6">
                            <x-input :name="'doctors['.$index.'][email]'" type="email" label="Email" :value="$row['email'] ?? ''" />
                        </div>
                        <div class="col-md-6">
                            <x-input :name="'doctors['.$index.'][password]'" type="password" label="Password" />
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <button type="button" class="btn btn-outline-primary mb-3" id="add-doctor">+ Tambah Dokter</button>
    @include('onboarding.partials.actions', ['canSkip' => true, 'step' => 'doctors'])
</form>

<template id="doctor-template">
    <div class="card mb-3 doctor-row">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="mb-0">Dokter</h5>
                <button type="button" class="btn btn-sm btn-outline-danger js-remove-row">Hapus</button>
            </div>
            <div class="row">
                <div class="col-md-6"><x-input name="doctors[__i__][name]" label="Nama" /></div>
                <div class="col-md-3"><x-input name="doctors[__i__][title]" label="Gelar" placeholder="drg." /></div>
                <div class="col-md-3"><x-input name="doctors[__i__][sip]" label="Nomor SIP" /></div>
                <div class="col-md-6"><x-input name="doctors[__i__][specialization]" label="Spesialisasi" /></div>
                <div class="col-md-6"><x-input name="doctors[__i__][phone]" label="Nomor Telepon" /></div>
                <div class="col-md-6"><x-input name="doctors[__i__][email]" type="email" label="Email" /></div>
                <div class="col-md-6"><x-input name="doctors[__i__][password]" type="password" label="Password" /></div>
            </div>
        </div>
    </div>
</template>

@push('scripts')
<script>
document.getElementById('add-doctor')?.addEventListener('click', () => {
    const list = document.getElementById('doctor-list');
    const tpl = document.getElementById('doctor-template').innerHTML.replaceAll('__i__', String(list.children.length));
    list.insertAdjacentHTML('beforeend', tpl);
});
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('js-remove-row')) {
        e.target.closest('.doctor-row,.staff-row,.medicine-row')?.remove();
    }
});
</script>
@endpush
