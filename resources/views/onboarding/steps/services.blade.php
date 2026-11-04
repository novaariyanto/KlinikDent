<div class="mb-4">
    <h3 class="mb-1">Layanan &amp; tarif</h3>
    <p class="text-muted">Gunakan contoh layanan generik, atau mulai dari kosong.</p>
</div>

<form method="POST" action="{{ route('onboarding.step', 'services') }}">
    @csrf
    <x-card>
        <div class="form-check mb-3">
            <input class="form-check-input" type="radio" name="mode" id="mode-template" value="template" @checked(old('mode', 'template') === 'template')>
            <label class="form-check-label" for="mode-template">
                Gunakan master tarif klinik gigi
                <div class="text-muted small">
                    {{ count(\App\Support\Onboarding\DentalTariffCatalog::items()) }} tindakan dari referensi tarif KlinikDent,
                    lengkap dengan jasa dokter, BHP, dan jasa klinik.
                </div>
            </label>
            <div class="mt-2 ps-1">
                @foreach (collect(\App\Support\Onboarding\DentalTariffCatalog::items())->groupBy('service') as $service => $rows)
                    <div class="small mb-1">
                        <span class="fw-semibold">{{ $service }}</span>
                        <span class="text-muted">— {{ $rows->pluck('procedure')->join(', ') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="mode" id="mode-empty" value="empty" @checked(old('mode') === 'empty')>
            <label class="form-check-label" for="mode-empty">Mulai dari kosong</label>
        </div>
        @include('onboarding.partials.actions', ['canSkip' => true, 'step' => 'services'])
    </x-card>
</form>
