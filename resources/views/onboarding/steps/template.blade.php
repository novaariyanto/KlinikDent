<div class="text-center mb-4">
    <h3 class="mb-1">Pilih tipe klinik Anda</h3>
    <p class="text-muted mb-0">Template ini hanya preset awal. Anda tetap bisa mengubahnya nanti.</p>
</div>

<div class="row g-3">
    @foreach (\App\Enums\OnboardingTemplate::cases() as $option)
        <div class="col-md-6">
            <form method="POST" action="{{ route('onboarding.step', 'template') }}">
                @csrf
                <input type="hidden" name="template" value="{{ $option->value }}">
                <button type="submit" class="btn p-0 w-100 text-start">
                    <div class="card onboarding-card-choice mb-0">
                        <div class="card-body p-4">
                            <div class="onboarding-icon mb-3"><i class="{{ $option->icon() }}"></i></div>
                            <h5 class="mb-2">{{ $option->label() }}</h5>
                            <p class="text-muted mb-3">{{ $option->description() }}</p>
                            <span class="btn btn-primary btn-sm">Pilih {{ $option->label() }}</span>
                        </div>
                    </div>
                </button>
            </form>
        </div>
    @endforeach
</div>
