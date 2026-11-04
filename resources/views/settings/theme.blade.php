@extends('layouts.app')

@section('title', 'Tema Tampilan')

@section('content')
    <x-page-header title="Tema Tampilan" :breadcrumb="['Pengaturan' => route('settings.clinic'), 'Tema' => null]" />

    <x-alert />
    @include('settings.partials.nav')

    <form method="POST" action="{{ route('settings.theme.update') }}" id="theme-form">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-xl-8">
                <x-card title="Template warna">
                    <p class="text-muted mb-3">Pilih tampilan untuk akun Anda. Pengaturan ini disimpan di sesi login dan tidak mengubah tema pengguna lain.</p>
                    <div class="row g-3">
                        @foreach ($presets as $key => $preset)
                            <div class="col-md-6">
                                <label class="theme-choice {{ old('theme_preset', $theme['preset']) === $key ? 'is-selected' : '' }}">
                                    <input
                                        type="radio"
                                        name="theme_preset"
                                        value="{{ $key }}"
                                        class="theme-choice__input"
                                        data-primary="{{ $preset['primary'] }}"
                                        data-navy="{{ $preset['navy'] }}"
                                        data-accent="{{ $preset['accent'] }}"
                                        @checked(old('theme_preset', $theme['preset']) === $key)
                                        @disabled(! $canManage)
                                    >
                                    <span class="theme-choice__swatches" aria-hidden="true">
                                        <span style="background: {{ $preset['navy'] }}"></span>
                                        <span style="background: {{ $preset['primary'] }}"></span>
                                        <span style="background: {{ $preset['accent'] }}"></span>
                                    </span>
                                    <span class="theme-choice__copy">
                                        <span class="theme-choice__title">{{ $preset['label'] }}</span>
                                        <span class="theme-choice__desc">{{ $preset['description'] }}</span>
                                    </span>
                                </label>
                            </div>
                        @endforeach
                        <div class="col-md-6">
                            <label class="theme-choice {{ old('theme_preset', $theme['preset']) === 'custom' ? 'is-selected' : '' }}">
                                <input
                                    type="radio"
                                    name="theme_preset"
                                    value="custom"
                                    class="theme-choice__input"
                                    @checked(old('theme_preset', $theme['preset']) === 'custom')
                                    @disabled(! $canManage)
                                >
                                <span class="theme-choice__swatches" aria-hidden="true">
                                    <span style="background: {{ $theme['custom']['navy'] }}"></span>
                                    <span style="background: {{ $theme['custom']['primary'] }}"></span>
                                    <span style="background: {{ $theme['custom']['accent'] }}"></span>
                                </span>
                                <span class="theme-choice__copy">
                                    <span class="theme-choice__title">Warna sendiri</span>
                                    <span class="theme-choice__desc">Atur warna utama, sidebar, dan aksen. Hindari hitam.</span>
                                </span>
                            </label>
                        </div>
                    </div>
                    @error('theme_preset')<div class="invalid-feedback d-block mt-2">{{ $message }}</div>@enderror
                </x-card>

                <x-card title="Warna sendiri">
                    <div class="row" id="theme-custom" data-enabled="{{ old('theme_preset', $theme['preset']) === 'custom' ? '1' : '0' }}">
                        <div class="col-md-4">
                            <label class="form-label" for="theme_primary">Warna utama</label>
                            <input type="color" class="form-control form-control-color w-100" id="theme_primary" name="theme_primary" value="{{ strtolower(old('theme_primary', $theme['custom']['primary'])) }}" @disabled(! $canManage || old('theme_preset', $theme['preset']) !== 'custom')>
                            @error('theme_primary')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="theme_navy">Sidebar</label>
                            <input type="color" class="form-control form-control-color w-100" id="theme_navy" name="theme_navy" value="{{ strtolower(old('theme_navy', $theme['custom']['navy'])) }}" @disabled(! $canManage || old('theme_preset', $theme['preset']) !== 'custom')>
                            @error('theme_navy')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="theme_accent">Aksen</label>
                            <input type="color" class="form-control form-control-color w-100" id="theme_accent" name="theme_accent" value="{{ strtolower(old('theme_accent', $theme['custom']['accent'])) }}" @disabled(! $canManage || old('theme_preset', $theme['preset']) !== 'custom')>
                            @error('theme_accent')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <p class="text-muted mb-0 mt-3">Warna utama dipakai tombol dan menu aktif. Navy dipakai sidebar dan header tabel. Aksen dipakai penanda singkat. Status sukses, tunggu, gagal, dan proses tidak berubah.</p>
                </x-card>

                @if ($canManage)
                    <div class="mb-4">
                        <x-button type="submit" icon="bx bx-save">Simpan Tema</x-button>
                    </div>
                @else
                    <div class="alert alert-info">Anda dapat melihat tema, tetapi tidak memiliki izin untuk mengubahnya.</div>
                @endif
            </div>

            <div class="col-xl-4">
                <x-card title="Pratinjau">
                    <div
                        class="theme-preview"
                        id="theme-preview"
                        style="--p: {{ $theme['primary'] }}; --n: {{ $theme['navy'] }}; --a: {{ $theme['accent'] }};"
                    >
                        <div class="theme-preview__bar">
                            <span>Menu aktif</span>
                            Antrean
                        </div>
                        <div class="theme-preview__body">
                            <div class="theme-preview__label">Pasien hari ini</div>
                            <div class="theme-preview__stat">24</div>
                            <div class="theme-preview__row">
                                <span class="theme-preview__name">Budi Santoso</span>
                                <span class="theme-preview__badge">Sedang diperiksa</span>
                            </div>
                            <div class="theme-preview__rm">RM-000123</div>
                            <button type="button" class="theme-preview__btn">Periksa</button>
                        </div>
                    </div>
                </x-card>
            </div>
        </div>
    </form>
@endsection

@push('styles')
<style>
    .theme-choice {
        display: flex;
        gap: 0.85rem;
        align-items: center;
        height: 100%;
        margin: 0;
        padding: 0.9rem 1rem;
        border: 1px solid var(--kd-border);
        border-radius: 10px;
        background: #fff;
        cursor: pointer;
    }
    .theme-choice.is-selected {
        border-color: var(--kd-primary);
        box-shadow: inset 0 0 0 1px var(--kd-primary);
    }
    .theme-choice__input {
        margin-top: 0.15rem;
        accent-color: var(--kd-primary);
    }
    .theme-choice__swatches {
        display: flex;
        width: 42px;
        height: 42px;
        border-radius: 8px;
        overflow: hidden;
        flex: 0 0 auto;
        border: 1px solid var(--kd-border);
    }
    .theme-choice__swatches span { flex: 1; }
    .theme-choice__title {
        display: block;
        font-weight: 700;
        color: var(--kd-navy);
    }
    .theme-choice__desc {
        display: block;
        color: var(--kd-muted);
        font-size: 0.8rem;
    }
    .theme-preview {
        border: 1px solid var(--kd-border);
        border-radius: 10px;
        overflow: hidden;
        background: #fff;
    }
    .theme-preview__bar {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 0.9rem;
        background: var(--n);
        color: #d5dee8;
        font-weight: 600;
    }
    .theme-preview__bar span {
        background: var(--p);
        color: #fff;
        font-weight: 700;
        border-radius: 6px;
        padding: 0.2rem 0.45rem;
        font-size: 0.75rem;
    }
    .theme-preview__body { padding: 0.95rem 1rem 1rem; }
    .theme-preview__label {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--kd-muted);
    }
    .theme-preview__stat {
        font-size: 1.85rem;
        font-weight: 800;
        letter-spacing: -0.03em;
        color: var(--n);
        line-height: 1.1;
    }
    .theme-preview__row {
        display: flex;
        justify-content: space-between;
        gap: 0.5rem;
        align-items: center;
        margin-top: 0.35rem;
    }
    .theme-preview__name { font-weight: 800; color: var(--n); }
    .theme-preview__rm { font-weight: 700; color: var(--p); margin: 0.15rem 0 0.75rem; }
    .theme-preview__badge {
        background: var(--a);
        color: #fff;
        font-weight: 700;
        font-size: 0.68rem;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        border-radius: 999px;
        padding: 0.25rem 0.45rem;
    }
    .theme-preview__btn {
        background: var(--p);
        color: #fff;
        border: 0;
        border-radius: 6px;
        font-weight: 700;
        padding: 0.45rem 0.9rem;
    }
    #theme-custom[data-enabled="0"] { opacity: 0.55; }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        var form = document.getElementById('theme-form');
        var preview = document.getElementById('theme-preview');
        var custom = document.getElementById('theme-custom');
        if (!form || !preview) return;

        var primary = document.getElementById('theme_primary');
        var navy = document.getElementById('theme_navy');
        var accent = document.getElementById('theme_accent');
        var canEdit = {{ $canManage ? 'true' : 'false' }};

        function paint(colors) {
            preview.style.setProperty('--p', colors.primary);
            preview.style.setProperty('--n', colors.navy);
            preview.style.setProperty('--a', colors.accent);
        }

        function selected() {
            return form.querySelector('input[name="theme_preset"]:checked');
        }

        function sync() {
            var input = selected();
            form.querySelectorAll('.theme-choice').forEach(function (label) {
                var radio = label.querySelector('input');
                label.classList.toggle('is-selected', !!(radio && radio.checked));
            });
            if (!input) return;
            var isCustom = input.value === 'custom';
            if (custom) custom.dataset.enabled = isCustom ? '1' : '0';
            [primary, navy, accent].forEach(function (field) {
                if (!field) return;
                field.disabled = !canEdit || !isCustom;
            });
            if (isCustom && primary && navy && accent) {
                paint({ primary: primary.value, navy: navy.value, accent: accent.value });
                return;
            }
            if (input.dataset.primary) {
                paint({
                    primary: input.dataset.primary,
                    navy: input.dataset.navy,
                    accent: input.dataset.accent
                });
            }
        }

        form.addEventListener('change', sync);
        [primary, navy, accent].forEach(function (field) {
            if (field) field.addEventListener('input', sync);
        });
        sync();
    })();
</script>
@endpush
