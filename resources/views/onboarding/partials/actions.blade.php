@php
    $canBack = ($stepNumber ?? 1) > 1;
    $canSkip = $canSkip ?? false;
    $continueLabel = $continueLabel ?? 'Lanjutkan';
@endphp

<div class="d-flex flex-wrap gap-2 justify-content-between mt-4">
    <div>
        @if ($canBack)
            <form method="POST" action="{{ route('onboarding.back') }}" class="d-inline">
                @csrf
                <x-button type="submit" variant="light" icon="bx bx-left-arrow-alt">Kembali</x-button>
            </form>
        @endif
    </div>
    <div class="d-flex gap-2">
        @if ($canSkip)
            <form method="POST" action="{{ route('onboarding.skip', $step) }}" class="d-inline">
                @csrf
                <x-button type="submit" variant="light">Lewati untuk sekarang</x-button>
            </form>
        @endif
        @if ($submit ?? true)
            <x-button type="submit" icon="bx bx-right-arrow-alt">{{ $continueLabel }}</x-button>
        @endif
    </div>
</div>
