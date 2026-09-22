#!/usr/bin/env bash

frontend_install() {
    if [[ -f "$ROOT/package-lock.json" ]]; then
        run_cmd "npm ci" npm ci
    else
        run_cmd "npm install" npm install
    fi
}

frontend_build() {
    run_cmd "npm run build" npm run build
}

validate_vite_manifest() {
    local manifest="$ROOT/public/build/manifest.json"

    if $DRY_RUN; then
        ok "public/build/manifest.json  (dry-run)"
        return
    fi

    if [[ ! -f "$manifest" ]]; then
        die "Frontend build failed. public/build/manifest.json tidak ditemukan."
    fi

    if [[ ! -s "$manifest" ]]; then
        die "Frontend build failed. manifest.json kosong."
    fi

    ok "public/build/manifest.json"
}

run_frontend() {
    step_header "Frontend..."

    if $SKIP_BUILD; then
        warn "Frontend dilewati (--skip-build)"
        if [[ ! -f "$ROOT/public/build/manifest.json" ]]; then
            warn "manifest.json belum ada — halaman admin Vite akan gagal"
        fi
        return
    fi

    frontend_install
    frontend_build
    validate_vite_manifest
}
