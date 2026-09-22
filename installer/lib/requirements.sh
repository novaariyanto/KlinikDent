#!/usr/bin/env bash

REQUIRED_PHP_EXTENSIONS=(
    bcmath
    ctype
    curl
    dom
    fileinfo
    filter
    gd
    hash
    json
    mbstring
    openssl
    pdo
    pdo_mysql
    session
    tokenizer
    xml
    zip
)

OPTIONAL_PHP_EXTENSIONS=(
    pcntl
    posix
)

command_exists() {
    command -v "$1" >/dev/null 2>&1
}

check_php() {
    if ! command_exists "$PHP_BIN"; then
        fail "PHP tidak ditemukan"
        die "ERROR: PHP ${PHP_MIN_VERSION}+ is required."
    fi

    local current
    current="$("$PHP_BIN" -r 'echo PHP_VERSION;')"
    if ! version_ge "$current" "$PHP_MIN_VERSION"; then
        fail "PHP ${PHP_MIN_VERSION}+ is required."
        echo "  Current version: $current"
        die "ERROR: PHP ${PHP_MIN_VERSION}+ is required."
    fi

    ok "PHP $current"
}

check_composer() {
    if command_exists composer; then
        COMPOSER_BIN="composer"
    elif command_exists composer.phar; then
        COMPOSER_BIN="composer.phar"
    elif [[ -f "$ROOT/composer.phar" ]]; then
        COMPOSER_BIN="$PHP_BIN $ROOT/composer.phar"
    else
        fail "Composer tidak ditemukan"
        die "ERROR: Composer is required."
    fi

    ok "Composer ($COMPOSER_BIN)"
}

check_node() {
    if ! command_exists node; then
        fail "Node.js tidak ditemukan"
        die "ERROR: Node.js ${NODE_MIN_VERSION}+ is required (Vite 6)."
    fi

    local current
    current="$(node -v | sed 's/^v//')"
    if ! version_ge "$current" "$NODE_MIN_VERSION"; then
        fail "Node.js ${NODE_MIN_VERSION}+ is required."
        echo "  Current version: $current"
        die "ERROR: Node.js ${NODE_MIN_VERSION}+ is required."
    fi

    ok "Node.js $current"
}

check_npm() {
    if ! command_exists npm; then
        fail "npm tidak ditemukan"
        die "ERROR: npm is required."
    fi
    ok "npm $(npm -v)"
}

check_mysql_client() {
    if command_exists mysql; then
        ok "MySQL client"
        return
    fi

    if command_exists mariadb; then
        ok "MariaDB client"
        return
    fi

    warn "mysql client tidak ada (koneksi tetap dicek via PDO)"
}

check_extensions() {
    local missing=()
    local ext

    for ext in "${REQUIRED_PHP_EXTENSIONS[@]}"; do
        if php_has_ext "$ext"; then
            continue
        fi
        missing+=("$ext")
    done

    if [[ ${#missing[@]} -gt 0 ]]; then
        fail "Required Extensions: ${missing[*]}"
        die "ERROR: PHP extensions missing: ${missing[*]}"
    fi

    ok "Required Extensions"

    for ext in "${OPTIONAL_PHP_EXTENSIONS[@]}"; do
        if php_has_ext "$ext"; then
            ok "$ext (queue worker)"
        else
            warn "$ext tidak ada — queue worker lebih baik dengan ekstensi ini"
        fi
    done
}

check_writable_dirs() {
    local dirs=(
        "$ROOT/storage"
        "$ROOT/storage/app"
        "$ROOT/storage/app/private"
        "$ROOT/storage/app/public"
        "$ROOT/storage/framework"
        "$ROOT/storage/framework/cache"
        "$ROOT/storage/framework/sessions"
        "$ROOT/storage/framework/views"
        "$ROOT/storage/logs"
        "$ROOT/bootstrap/cache"
    )
    local dir failed=0

    for dir in "${dirs[@]}"; do
        mkdir -p "$dir" 2>/dev/null || true
        if [[ ! -w "$dir" ]]; then
            fail "Tidak writable: $dir"
            failed=1
        fi
    done

    if [[ $failed -ne 0 ]]; then
        die "ERROR: Storage permission tidak mencukupi."
    fi

    ok "Storage Permission"
}

print_system_header() {
    local app_name app_env php_ver
    app_name="$(env_get APP_NAME KlinikDent)"
    app_env="$(env_get APP_ENV local)"
    php_ver="$("$PHP_BIN" -r 'echo PHP_VERSION;' 2>/dev/null || echo 'n/a')"

    banner "Laravel Application Installer"
    echo
    echo "Application : ${app_name:-KlinikDent}"
    echo "Laravel     : 12.x"
    echo "PHP         : $php_ver"
    echo "Environment : ${app_env:-unknown}"
    echo
    section "System Check"
}

run_requirements() {
    step_header "Checking requirements..."
    print_system_header

    check_php
    check_composer
    check_node
    check_npm
    check_mysql_client
    check_extensions
    check_writable_dirs

    if php_has_ext redis; then
        warn "ext-redis terpasang, tetapi project memakai CACHE/QUEUE/SESSION database — Redis tidak wajib"
    fi
}
