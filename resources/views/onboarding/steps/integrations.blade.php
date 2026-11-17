@php
    $whatsappAddon = $whatsappAddon ?? null;
    $whatsappCanActivateNow = $whatsappAddon
        && ($whatsappAddon->isFree() || (int) $whatsappAddon->trial_days > 0);
@endphp

<div class="mb-4">
    <h3 class="mb-1">Integrasi & Add-on <span class="badge bg-soft-primary text-primary">Opsional</span></h3>
    <p class="text-muted mb-0">SATUSEHAT, BPJS, dan WhatsApp tidak wajib. Modul WhatsApp hanya muncul setelah Add-on diaktifkan.</p>
</div>

<form method="POST" action="{{ route('onboarding.step', 'integrations') }}">
    @csrf
    <input type="hidden" name="satusehat_action" value="skip">
    <input type="hidden" name="bpjs_action" value="skip">
    <input type="hidden" name="whatsapp_action" value="skip">
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
        <div class="col-md-12">
            <x-card title="WhatsApp Integration (Add-on)">
                <p class="text-muted small mb-3">
                    Pengingat jadwal, promosi, dan otomasi WhatsApp. Menu WhatsApp hanya tampil setelah Add-on aktif.
                    @if ($whatsappAddon && ! $whatsappAddon->isFree())
                        Harga: <strong>Rp {{ number_format((float) $whatsappAddon->price, 0, ',', '.') }}</strong>
                        / {{ $whatsappAddon->interval->label() }}.
                    @endif
                </p>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="whatsapp_action" id="wa-skip" value="skip" @checked(old('whatsapp_action', 'skip') === 'skip')>
                    <label class="form-check-label" for="wa-skip">Lewati dulu (menu WhatsApp tetap tersembunyi)</label>
                </div>
                @if ($whatsappCanActivateNow)
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="whatsapp_action" id="wa-activate" value="activate" @checked(old('whatsapp_action') === 'activate')>
                        <label class="form-check-label" for="wa-activate">
                            Aktifkan Add-on sekarang
                            @if ((int) ($whatsappAddon?->trial_days ?? 0) > 0 && ! $whatsappAddon?->isFree())
                                (trial {{ $whatsappAddon->trial_days }} hari)
                            @else
                                (gratis)
                            @endif
                        </label>
                    </div>
                @else
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="whatsapp_action" id="wa-later" value="later" @checked(old('whatsapp_action') === 'later')>
                        <label class="form-check-label" for="wa-later">Nanti lewat Add-ons / Marketplace (berbayar)</label>
                    </div>
                    <p class="text-muted small mb-0">Setelah setup selesai, Owner dapat mengaktifkan Add-on WhatsApp dari menu <strong>Add-ons → Marketplace</strong>.</p>
                @endif
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
