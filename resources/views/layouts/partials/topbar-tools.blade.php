@php
    $theme = \App\Support\ClinicTheme::current();
    $themePresets = \App\Support\ClinicTheme::presets();
@endphp

<button type="button" class="btn header-item noti-icon waves-effect" id="header-fullscreen" title="Layar penuh" aria-label="Layar penuh">
    <i class="bx bx-fullscreen"></i>
</button>

@if (auth()->check())
    <div class="dropdown d-inline-block">
        <button type="button" class="btn header-item noti-icon waves-effect" id="header-theme"
            data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Tema tampilan" aria-label="Tema tampilan">
            <i class="bx bx-palette"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-end theme-menu">
            <div class="theme-menu__title">Tema tampilan</div>
            <div class="theme-menu__list" id="theme-menu-presets">
                @foreach ($themePresets as $key => $preset)
                    <button type="button" class="theme-menu__item {{ $theme['preset'] === $key ? 'is-active' : '' }}"
                        data-preset="{{ $key }}">
                        <span class="theme-menu__swatches" aria-hidden="true">
                            <span style="background: {{ $preset['navy'] }}"></span>
                            <span style="background: {{ $preset['primary'] }}"></span>
                            <span style="background: {{ $preset['accent'] }}"></span>
                        </span>
                        <span class="theme-menu__label">{{ $preset['label'] }}</span>
                        <i class="bx bx-check theme-menu__check"></i>
                    </button>
                @endforeach
            </div>
            <div class="theme-menu__custom">
                <div class="theme-menu__title">Warna sendiri</div>
                <div class="theme-menu__colors">
                    <label>
                        Utama
                        <input type="color" id="topbar-theme-primary" value="{{ strtolower($theme['custom']['primary']) }}">
                    </label>
                    <label>
                        Sidebar
                        <input type="color" id="topbar-theme-navy" value="{{ strtolower($theme['custom']['navy']) }}">
                    </label>
                    <label>
                        Aksen
                        <input type="color" id="topbar-theme-accent" value="{{ strtolower($theme['custom']['accent']) }}">
                    </label>
                </div>
                <button type="button" class="btn btn-primary btn-sm w-100" id="topbar-theme-apply">Terapkan</button>
                <p class="theme-menu__note mb-0">Tema ini hanya untuk akun Anda dan berlaku selama sesi login.</p>
                <p class="theme-menu__status mb-0" id="theme-menu-status" role="status"></p>
            </div>
        </div>
    </div>
@endif

<script>
    (function () {
        var fullscreenBtn = document.getElementById('header-fullscreen');
        if (fullscreenBtn) {
            var fullscreenIcon = fullscreenBtn.querySelector('i');

            function setFullscreenIcon(on) {
                if (!fullscreenIcon) return;
                fullscreenIcon.classList.toggle('bx-fullscreen', !on);
                fullscreenIcon.classList.toggle('bx-exit-fullscreen', on);
                fullscreenBtn.title = on ? 'Keluar layar penuh' : 'Layar penuh';
                fullscreenBtn.setAttribute('aria-label', fullscreenBtn.title);
            }

            fullscreenBtn.addEventListener('click', function (event) {
                event.preventDefault();
                var root = document.documentElement;
                var active = document.fullscreenElement || document.webkitFullscreenElement;
                try {
                    if (!active) {
                        var request = root.requestFullscreen || root.webkitRequestFullscreen || root.mozRequestFullScreen || root.msRequestFullscreen;
                        if (!request) return;
                        Promise.resolve(request.call(root)).then(function () {
                            document.body.classList.add('fullscreen-enable');
                            setFullscreenIcon(true);
                        }).catch(function () {});
                        return;
                    }
                    var exit = document.exitFullscreen || document.webkitExitFullscreen || document.mozCancelFullScreen || document.msExitFullscreen;
                    if (!exit) return;
                    Promise.resolve(exit.call(document)).then(function () {
                        document.body.classList.remove('fullscreen-enable');
                        setFullscreenIcon(false);
                    }).catch(function () {});
                } catch (e) {}
            });

            document.addEventListener('fullscreenchange', function () {
                var on = !!document.fullscreenElement;
                document.body.classList.toggle('fullscreen-enable', on);
                setFullscreenIcon(on);
            });
        }

        var themeRoot = document.getElementById('header-theme');
        if (!themeRoot) return;

        var status = document.getElementById('theme-menu-status');
        var primary = document.getElementById('topbar-theme-primary');
        var navy = document.getElementById('topbar-theme-navy');
        var accent = document.getElementById('topbar-theme-accent');
        var apply = document.getElementById('topbar-theme-apply');
        var token = document.querySelector('meta[name="csrf-token"]');

        function mark(preset) {
            document.querySelectorAll('#theme-menu-presets .theme-menu__item').forEach(function (button) {
                button.classList.toggle('is-active', button.getAttribute('data-preset') === preset);
            });
        }

        function save(payload) {
            if (status) {
                status.textContent = 'Menyimpan...';
                status.className = 'theme-menu__status mb-0';
            }
            return fetch(@json(route('settings.theme.update')), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token ? token.getAttribute('content') : ''
                },
                body: JSON.stringify(payload)
            }).then(function (response) {
                return response.json().then(function (body) {
                    return { ok: response.ok, body: body };
                });
            }).then(function (result) {
                if (!result.ok) {
                    var errors = result.body && result.body.errors ? result.body.errors : null;
                    var message = errors ? Object.values(errors).flat()[0] : (result.body && result.body.message) || 'Tema gagal disimpan.';
                    throw new Error(message);
                }
                var node = document.getElementById('clinic-theme');
                if (node && result.body.style) node.textContent = result.body.style;
                if (result.body.theme) mark(result.body.theme.preset);
                if (status) {
                    status.textContent = result.body.message || 'Tema tersimpan.';
                    status.className = 'theme-menu__status mb-0 is-ok';
                }
            }).catch(function (error) {
                if (status) {
                    status.textContent = error.message || 'Tema gagal disimpan.';
                    status.className = 'theme-menu__status mb-0 is-error';
                }
            });
        }

        document.querySelectorAll('#theme-menu-presets .theme-menu__item').forEach(function (button) {
            button.addEventListener('click', function () {
                save({ theme_preset: button.getAttribute('data-preset') });
            });
        });

        if (apply) {
            apply.addEventListener('click', function () {
                save({
                    theme_preset: 'custom',
                    theme_primary: primary.value,
                    theme_navy: navy.value,
                    theme_accent: accent.value
                });
            });
        }
    })();
</script>

<style>
    .theme-menu { width: 280px; padding: 0.75rem 0.75rem 0.85rem; }
    .theme-menu__title {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        color: var(--kd-muted, #3a4b5c);
        margin-bottom: 0.45rem;
    }
    .theme-menu__item {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        width: 100%;
        margin: 0 0 0.35rem;
        padding: 0.4rem 0.45rem;
        border: 1px solid var(--kd-border, #c5d0d8);
        border-radius: 8px;
        background: #fff;
        text-align: left;
        font-weight: 700;
        color: var(--kd-navy, #00695c);
    }
    .theme-menu__item.is-active {
        border-color: var(--kd-primary, #00796b);
        box-shadow: inset 0 0 0 1px var(--kd-primary, #00796b);
    }
    .theme-menu__swatches {
        display: flex;
        width: 36px;
        height: 22px;
        border-radius: 4px;
        overflow: hidden;
        border: 1px solid var(--kd-border, #c5d0d8);
        flex: 0 0 auto;
    }
    .theme-menu__swatches span { flex: 1; }
    .theme-menu__label { flex: 1; }
    .theme-menu__check { color: var(--kd-primary, #00796b); opacity: 0; }
    .theme-menu__item.is-active .theme-menu__check { opacity: 1; }
    .theme-menu__custom { border-top: 1px solid var(--kd-border, #c5d0d8); margin-top: 0.35rem; padding-top: 0.65rem; }
    .theme-menu__colors { display: flex; gap: 0.45rem; margin-bottom: 0.65rem; }
    .theme-menu__colors label {
        flex: 1;
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--kd-navy, #00695c);
    }
    .theme-menu__colors input { display: block; width: 100%; height: 32px; padding: 0.15rem; margin-top: 0.2rem; }
    .theme-menu__status { min-height: 1.1rem; margin-top: 0.45rem; font-size: 0.75rem; font-weight: 600; }
    .theme-menu__status.is-ok { color: var(--kd-success, #0e7a46); }
    .theme-menu__status.is-error { color: var(--kd-danger, #c62828); }
    .theme-menu__note { font-size: 0.75rem; color: var(--kd-muted, #3a4b5c); }
</style>
