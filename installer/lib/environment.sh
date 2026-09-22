#!/usr/bin/env bash

REQUIRED_ENV_KEYS=(
    APP_NAME
    APP_ENV
    APP_DEBUG
    APP_URL
    DB_CONNECTION
    DB_HOST
    DB_PORT
    DB_DATABASE
    DB_USERNAME
)

ENV_PROMPT_KEYS=(
    APP_NAME
    APP_ENV
    APP_DEBUG
    APP_URL
    APP_FORCE_HTTPS
    DB_CONNECTION
    DB_HOST
    DB_PORT
    DB_DATABASE
    DB_USERNAME
    DB_PASSWORD
    QUEUE_CONNECTION
    SESSION_DRIVER
    CACHE_STORE
    FILESYSTEM_DISK
    ADMIN_EMAIL
    ADMIN_PASSWORD
)

is_secret_env() {
    [[ "$1" =~ $SECRET_ENV_KEYS_REGEX ]]
}

copy_env_example() {
    if [[ ! -f "$ROOT/.env.example" ]]; then
        die "ERROR: .env.example tidak ditemukan."
    fi

    plan "cp .env.example .env"
    if $DRY_RUN; then
        ok ".env akan disalin dari .env.example"
        return
    fi

    cp "$ROOT/.env.example" "$ROOT/.env"
    ok ".env dibuat dari .env.example"
    log "Copied .env.example -> .env"
}

reconfigure_env() {
    local key current value secret

    if $DRY_RUN; then
        ok "Reconfigure .env (dry-run)"
        return
    fi

    if [[ -f "$(env_file)" ]]; then
        backup_file "$(env_file)" >/dev/null
        echo "  Backup .env dibuat sebelum perubahan."
    fi

    echo
    echo "Isi konfigurasi. Enter = pertahankan nilai saat ini."
    echo "Nilai rahasia tidak ditampilkan."
    echo

    for key in "${ENV_PROMPT_KEYS[@]}"; do
        current="$(env_get "$key")"
        secret=false
        is_secret_env "$key" && secret=true
        value="$(prompt_value "$key" "$current" "$secret")"
        env_set "$key" "$value"
    done

    local midtrans
    midtrans="$(env_get MIDTRANS_ENABLED false)"
    if [[ "$midtrans" == "true" || "$midtrans" == "1" ]]; then
        echo
        echo "MIDTRANS_ENABLED=true — kredensial payment gateway:"
        for key in MIDTRANS_IS_PRODUCTION MIDTRANS_MERCHANT_ID MIDTRANS_CLIENT_KEY MIDTRANS_SERVER_KEY; do
            current="$(env_get "$key")"
            secret=false
            is_secret_env "$key" && secret=true
            value="$(prompt_value "$key" "$current" "$secret")"
            env_set "$key" "$value"
        done
    fi

    ok ".env diperbarui"
}

validate_required_env() {
    local key missing=()
    local value

    for key in "${REQUIRED_ENV_KEYS[@]}"; do
        value="$(env_get "$key")"
        if [[ -z "$value" ]]; then
            missing+=("$key")
        fi
    done

    if [[ ${#missing[@]} -gt 0 ]]; then
        fail "Env wajib kosong: ${missing[*]}"
        echo
        echo "Isi variable tersebut di .env lalu jalankan installer lagi."
        die "ERROR: Required environment variables are missing."
    fi

    if [[ "$(env_get DB_CONNECTION mysql)" != "mysql" && "$(env_get DB_CONNECTION)" != "mariadb" ]]; then
        die "ERROR: Project ini memakai MySQL 8+. Set DB_CONNECTION=mysql."
    fi

    if [[ "$(env_get APP_ENV local)" == "production" && "$(env_get APP_DEBUG true)" == "true" ]]; then
        warn "APP_DEBUG=true pada production — ini tidak aman"
        if ! $DRY_RUN; then
            if confirm "Set APP_DEBUG=false sekarang?" Y; then
                env_set APP_DEBUG false
                ok "APP_DEBUG=false"
            else
                die "ERROR: Production tidak boleh APP_DEBUG=true."
            fi
        fi
    fi

    if [[ "$(env_get APP_ENV local)" == "production" && "$(env_get APP_FORCE_HTTPS false)" != "true" ]]; then
        warn "APP_FORCE_HTTPS belum true (disarankan untuk production HTTPS)"
    fi

    if [[ "$(env_get APP_ENV local)" == "production" && "$(env_get QUEUE_CONNECTION database)" == "sync" ]]; then
        warn "QUEUE_CONNECTION=sync — job SATUSEHAT/BPJS/WhatsApp tidak akan antre"
        if ! $DRY_RUN; then
            if confirm "Set QUEUE_CONNECTION=database sekarang?" Y; then
                env_set QUEUE_CONNECTION database
            fi
        fi
    fi

    if [[ "$(env_get MIDTRANS_ENABLED false)" == "true" ]]; then
        if [[ -z "$(env_get MIDTRANS_SERVER_KEY)" || -z "$(env_get MIDTRANS_CLIENT_KEY)" ]]; then
            die "ERROR: MIDTRANS_ENABLED=true tetapi MIDTRANS_SERVER_KEY / MIDTRANS_CLIENT_KEY kosong."
        fi
    fi

    local disk queue cache session
    disk="$(env_get FILESYSTEM_DISK local)"
    queue="$(env_get QUEUE_CONNECTION database)"
    cache="$(env_get CACHE_STORE database)"
    session="$(env_get SESSION_DRIVER database)"

    if [[ "$disk" == "s3" ]]; then
        if [[ -z "$(env_get AWS_BUCKET)" || -z "$(env_get AWS_ACCESS_KEY_ID)" ]]; then
            die "ERROR: FILESYSTEM_DISK=s3 memerlukan AWS_BUCKET dan AWS_ACCESS_KEY_ID."
        fi
    fi

    if [[ "$queue" == "redis" || "$cache" == "redis" || "$session" == "redis" ]]; then
        if [[ -z "$(env_get REDIS_HOST)" ]]; then
            die "ERROR: Driver redis aktif tetapi REDIS_HOST kosong."
        fi
    fi

    ok "Env wajib terisi"
}

choose_existing_env() {
    local choice

    echo ".env already exists."
    echo
    echo "Options:"
    echo
    echo "1. Use existing .env"
    echo "2. Reconfigure .env"
    echo "3. Exit"
    echo

    if $FORCE || $NON_INTERACTIVE; then
        ok "Use existing .env (--force)"
        return
    fi

    if $DRY_RUN; then
        ok "Use existing .env (dry-run)"
        return
    fi

    read -r -p "Pilih [1]: " choice || true
    choice="${choice:-1}"

    case "$choice" in
        2) reconfigure_env ;;
        3) echo "Dibatalkan."; exit 0 ;;
        *) ok "Use existing .env" ;;
    esac
}

run_environment() {
    step_header "Environment..."

    if [[ ! -f "$(env_file)" ]]; then
        echo ".env belum ada — menyalin dari .env.example"
        copy_env_example
        if ! $DRY_RUN; then
            reconfigure_env
        fi
    else
        choose_existing_env
    fi

    if $DRY_RUN && [[ ! -f "$(env_file)" ]]; then
        warn ".env belum ada — validasi env dilewati di dry-run"
        return
    fi

    validate_required_env
}
