<div class="mb-4">
    <h3 class="mb-1">Integrasi <span class="badge bg-soft-primary text-primary">Opsional</span></h3>
    <p class="text-muted mb-0">SATUSEHAT dan BPJS tidak wajib. Lewati saja jika belum diperlukan — bisa diatur nanti lewat menu Integrasi.</p>
</div>

<form method="POST" action="{{ route('onboarding.step', 'integrations') }}">
    @csrf
    <input type="hidden" name="satusehat_action" value="skip">
    <input type="hidden" name="bpjs_action" value="skip">
    <div class="row g-3">
        <div class="col-md-6">
            <x-card title="SATUSEHAT">
                <p class="text-muted small">Pengiriman data kunjungan ke SATUSEHAT.</p>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="satusehat_action" id="ss-skip" value="skip" @checked(old('satusehat_action', 'skip') === 'skip')>
                    <label class="form-check-label" for="ss-skip">Lewati dulu</label>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="satusehat_action" id="ss-configure" value="configure" @checked(old('satusehat_action') === 'configure')>
                    <label class="form-check-label" for="ss-configure">Konfigurasi sekarang</label>
                </div>
                <div id="ss-fields" class="{{ old('satusehat_action') === 'configure' ? '' : 'd-none' }}">
                    <x-input name="satusehat[client_id]" label="Client ID" :value="old('satusehat.client_id')" />
                    <x-input name="satusehat[client_secret]" type="password" label="Client Secret" />
                    <x-input name="satusehat[organization_id]" label="Organization ID" :value="old('satusehat.organization_id')" />
                    <x-input name="satusehat[base_url]" label="Base URL" :value="old('satusehat.base_url')" />
                    <x-input name="satusehat[token_url]" label="Token URL" :value="old('satusehat.token_url')" />
                </div>
            </x-card>
        </div>
        <div class="col-md-6">
            <x-card title="BPJS VClaim">
                <p class="text-muted small">Cek kepesertaan dan klaim BPJS.</p>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="bpjs_action" id="bpjs-skip" value="skip" @checked(old('bpjs_action', 'skip') === 'skip')>
                    <label class="form-check-label" for="bpjs-skip">Lewati dulu</label>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="bpjs_action" id="bpjs-configure" value="configure" @checked(old('bpjs_action') === 'configure')>
                    <label class="form-check-label" for="bpjs-configure">Konfigurasi sekarang</label>
                </div>
                <div id="bpjs-fields" class="{{ old('bpjs_action') === 'configure' ? '' : 'd-none' }}">
                    <x-input name="bpjs[cons_id]" label="Consumer ID" :value="old('bpjs.cons_id')" />
                    <x-input name="bpjs[secret_key]" type="password" label="Secret Key" />
                    <x-input name="bpjs[user_key]" type="password" label="User Key" />
                    <x-input name="bpjs[base_url]" label="Base URL" :value="old('bpjs.base_url')" />
                </div>
            </x-card>
        </div>
    </div>
    @include('onboarding.partials.actions', ['canSkip' => true, 'step' => 'integrations', 'continueLabel' => 'Lanjutkan tanpa konfigurasi'])
</form>

@push('scripts')
<script>
document.getElementById('ss-skip')?.addEventListener('change', () => document.getElementById('ss-fields').classList.add('d-none'));
document.getElementById('ss-configure')?.addEventListener('change', () => document.getElementById('ss-fields').classList.remove('d-none'));
document.getElementById('bpjs-skip')?.addEventListener('change', () => document.getElementById('bpjs-fields').classList.add('d-none'));
document.getElementById('bpjs-configure')?.addEventListener('change', () => document.getElementById('bpjs-fields').classList.remove('d-none'));
</script>
@endpush
