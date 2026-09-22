<div class="mb-4">
    <h3 class="mb-1">Role yang disarankan</h3>
    <p class="text-muted">Role dapat diubah kembali kapan saja melalui Users &amp; Roles.</p>
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
