<div class="mb-4">
    <h3 class="mb-1">Role yang disarankan</h3>
    <p class="text-muted">
        Role dapat diubah kembali kapan saja melalui Users &amp; Roles.
        @if (($template ?? null) instanceof \App\Enums\OnboardingTemplate && $template === \App\Enums\OnboardingTemplate::Small)
            Untuk klinik kecil: Owner + Admin Operasional + Perawat + Dokter Gigi sudah cukup. Memilih Admin Operasional otomatis menyertakan akses Perawat.
        @endif
    </p>
</div>

<form method="POST" action="{{ route('onboarding.step', 'roles') }}">
    @csrf

    <x-card title="Role yang disarankan">
        @foreach ($recommendedRoles as $role)
            @php $enum = \App\Enums\RoleName::tryFrom($role); @endphp
            <div class="form-check mb-2">
                <input
                    class="form-check-input"
                    type="checkbox"
                    name="roles[]"
                    id="role-{{ $role }}"
                    value="{{ $role }}"
                    @checked(in_array($role, old('roles', $selectedRoles), true))
                    @if ($role === \App\Enums\RoleName::Owner->value) onclick="return false;" @endif
                >
                <label class="form-check-label" for="role-{{ $role }}">{{ $enum?->label() ?? $role }}</label>
            </div>
        @endforeach
    </x-card>

    @if (count($otherRoles))
        <x-card title="Role lainnya" class="mt-3">
            @foreach ($otherRoles as $role)
                @php $enum = \App\Enums\RoleName::tryFrom($role); @endphp
                <div class="form-check mb-2">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="roles[]"
                        id="role-{{ $role }}"
                        value="{{ $role }}"
                        @checked(in_array($role, old('roles', $selectedRoles), true))
                    >
                    <label class="form-check-label" for="role-{{ $role }}">{{ $enum?->label() ?? $role }}</label>
                </div>
            @endforeach
        </x-card>
    @endif

    @include('onboarding.partials.actions')
</form>

@push('scripts')
<script>
document.querySelector('form[action*="roles"]')?.addEventListener('change', (event) => {
    const input = event.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'checkbox') return;
    if (input.value !== @json(\App\Enums\RoleName::ClinicOps->value) || !input.checked) return;
    const nurse = document.querySelector('input[type="checkbox"][name="roles[]"][value="' + @json(\App\Enums\RoleName::Nurse->value) + '"]');
    if (nurse) nurse.checked = true;
});
</script>
@endpush
