@php
    /** @var \App\Models\SaasPackage|null $package */
    $package = $package ?? null;
    $formKey = $formKey ?? 'create';
    $active = old('_form') === $formKey;
    $field = function (string $name, mixed $default = null) use ($active) {
        return $active ? old($name, $default) : $default;
    };
    $invalid = fn (string $name): bool => $active && $errors->has($name);
    $isActive = $active
        ? filter_var($field('is_active', $package?->is_active ?? true), FILTER_VALIDATE_BOOLEAN)
        : (bool) ($package?->is_active ?? true);
    $interval = (string) $field('interval', $package?->interval?->value ?? 'monthly');
@endphp

<input type="hidden" name="_form" value="{{ $formKey }}">

<div class="row g-2">
    <div class="col-md-4">
        <div class="mb-3">
            <label class="form-label" for="{{ $formKey }}-name">Nama <span class="text-danger">*</span></label>
            <input
                class="form-control {{ $invalid('name') ? 'is-invalid' : '' }}"
                id="{{ $formKey }}-name"
                name="name"
                value="{{ $field('name', $package?->name) }}"
                required
                maxlength="255"
            >
            @if ($invalid('name'))
                <div class="invalid-feedback d-block">{{ $errors->first('name') }}</div>
            @endif
        </div>
    </div>
    <div class="col-md-2">
        <div class="mb-3">
            <label class="form-label" for="{{ $formKey }}-price">Harga <span class="text-danger">*</span></label>
            <input
                class="form-control {{ $invalid('price') ? 'is-invalid' : '' }}"
                id="{{ $formKey }}-price"
                name="price"
                type="number"
                min="0"
                step="1"
                value="{{ $field('price', $package ? (float) $package->price : 0) }}"
                required
            >
            @if ($invalid('price'))
                <div class="invalid-feedback d-block">{{ $errors->first('price') }}</div>
            @endif
        </div>
    </div>
    <div class="col-md-2">
        <div class="mb-3">
            <label class="form-label" for="{{ $formKey }}-interval">Periode <span class="text-danger">*</span></label>
            <select class="form-select {{ $invalid('interval') ? 'is-invalid' : '' }}" id="{{ $formKey }}-interval" name="interval" required>
                @foreach (\App\Http\Controllers\Saas\PackageController::intervalOptions() as $value => $label)
                    <option value="{{ $value }}" @selected($interval === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
            @if ($invalid('interval'))
                <div class="invalid-feedback d-block">{{ $errors->first('interval') }}</div>
            @endif
        </div>
    </div>
    <div class="col-md-2">
        <div class="mb-3">
            <label class="form-label" for="{{ $formKey }}-trial">Hari trial <span class="text-danger">*</span></label>
            <input
                class="form-control {{ $invalid('trial_days') ? 'is-invalid' : '' }}"
                id="{{ $formKey }}-trial"
                name="trial_days"
                type="number"
                min="0"
                max="365"
                value="{{ $field('trial_days', $package?->trial_days ?? 14) }}"
                required
            >
            @if ($invalid('trial_days'))
                <div class="invalid-feedback d-block">{{ $errors->first('trial_days') }}</div>
            @endif
        </div>
    </div>
    <div class="col-md-2">
        <div class="mb-3">
            <label class="form-label" for="{{ $formKey }}-active">Status</label>
            <div class="form-check mt-2">
                <input type="hidden" name="is_active" value="0">
                <input class="form-check-input" type="checkbox" name="is_active" id="{{ $formKey }}-active" value="1" @checked($isActive)>
                <label class="form-check-label" for="{{ $formKey }}-active">Aktif</label>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="mb-3">
            <label class="form-label" for="{{ $formKey }}-branches">Maks. cabang</label>
            <input
                class="form-control {{ $invalid('max_branches') ? 'is-invalid' : '' }}"
                id="{{ $formKey }}-branches"
                name="max_branches"
                type="number"
                min="1"
                value="{{ $field('max_branches', $package?->max_branches) }}"
                placeholder="Tidak dibatasi"
            >
            @if ($invalid('max_branches'))
                <div class="invalid-feedback d-block">{{ $errors->first('max_branches') }}</div>
            @endif
        </div>
    </div>
    <div class="col-md-3">
        <div class="mb-3">
            <label class="form-label" for="{{ $formKey }}-users">Maks. pengguna</label>
            <input
                class="form-control {{ $invalid('max_users') ? 'is-invalid' : '' }}"
                id="{{ $formKey }}-users"
                name="max_users"
                type="number"
                min="1"
                value="{{ $field('max_users', $package?->max_users) }}"
                placeholder="Tidak dibatasi"
            >
            @if ($invalid('max_users'))
                <div class="invalid-feedback d-block">{{ $errors->first('max_users') }}</div>
            @endif
        </div>
    </div>
</div>
