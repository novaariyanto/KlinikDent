@extends('layouts.onboarding')

@section('title', 'Mari siapkan klinik Anda')

@section('content')
    <div class="onboarding-step">
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="text-muted small">Langkah {{ $stepNumber }} dari {{ $totalSteps }}</span>
                <span class="fw-semibold small">{{ $progress }}%</span>
            </div>
            <div class="progress onboarding-progress">
                <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $progress }}%"></div>
            </div>
        </div>

        @if ($tenant->onboarding_status?->value === 'in_progress' && $stepNumber > 1)
            <div class="alert alert-info">Lanjutkan setup klinik Anda</div>
        @endif

        <x-alert />

        @include('onboarding.steps.'.$step)
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('js-remove-row')) {
        e.target.closest('.doctor-row,.staff-row,.medicine-row')?.remove();
    }
});
</script>
@endpush
