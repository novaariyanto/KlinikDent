#!/usr/bin/env bash

print_db_meta() {
    local host port database username
    host="$(env_get DB_HOST 127.0.0.1)"
    port="$(env_get DB_PORT 3306)"
    database="$(env_get DB_DATABASE)"
    username="$(env_get DB_USERNAME)"

    section "Database Connection"
    echo "Host       : $host"
    echo "Port       : $port"
    echo "Database   : $database"
    echo "Username   : $username"
}

ensure_database_exists() {
    local exists
    exists="$(php_helper check-db.php exists 2>/dev/null || true)"

    if [[ "$exists" == "yes" ]]; then
        return 0
    fi

    warn "Database $(env_get DB_DATABASE) belum ada."

    if $DRY_RUN; then
        plan "CREATE DATABASE $(env_get DB_DATABASE)"
        return 0
    fi

    if confirm "Buat database sekarang?" Y; then
        if php_helper check-db.php create; then
            ok "Database dibuat"
            log "Created database $(env_get DB_DATABASE)"
            return 0
        fi
        die "ERROR: Gagal membuat database. Cek hak akses MySQL."
    fi

    die "ERROR: Database belum ada. Buat secara manual lalu jalankan installer lagi."
}

check_database_connection() {
    print_db_meta

    if $DRY_RUN && [[ ! -f "$(env_file)" ]]; then
        warn "Lewati cek koneksi (tidak ada .env)"
        return
    fi

    if ! php_helper check-db.php server >/dev/null; then
        echo
        echo "Database connection failed."
        echo
        echo "Please verify:"
        echo
        echo "DB_HOST"
        echo "DB_PORT"
        echo "DB_DATABASE"
        echo "DB_USERNAME"
        echo "DB_PASSWORD"
        echo
        php_helper check-db.php server >/dev/null || true
        die "ERROR: Tidak dapat terhubung ke server MySQL."
    fi

    ensure_database_exists

    if $DRY_RUN; then
        if php_helper check-db.php ping >/dev/null 2>&1; then
            echo "Connection: ✓ SUCCESS"
            ok "Connection successful"
        else
            warn "Ping schema dilewati di dry-run (database mungkin belum ada)"
        fi
        return
    fi

    if php_helper check-db.php ping >/dev/null; then
        echo "Connection: ✓ SUCCESS"
        ok "Connection successful"
        return
    fi

    echo
    echo "Database connection failed."
    echo
    echo "Please verify:"
    echo
    echo "DB_HOST"
    echo "DB_PORT"
    echo "DB_DATABASE"
    echo "DB_USERNAME"
    echo "DB_PASSWORD"
    die "ERROR: Koneksi ke database gagal."
}

database_user_count() {
    php_helper check-db.php users 2>/dev/null || echo "0"
}

database_has_migrations() {
    [[ "$(php_helper check-db.php migrations 2>/dev/null || echo no)" == "yes" ]]
}

maybe_backup_database() {
    if ! database_has_migrations; then
        return
    fi

    local default_backup=N
    if [[ "$(env_get APP_ENV local)" == "production" ]]; then
        default_backup=Y
    fi

    echo
    echo "Database migration detected."
    echo
    echo "Create database backup before migration?"
    echo

    if ! confirm "Backup database sekarang?" "$default_backup"; then
        return
    fi

    if $DRY_RUN; then
        plan "php artisan app:backup"
        ok "Backup database (dry-run)"
        return
    fi

    if [[ ! -d "$ROOT/vendor" ]]; then
        warn "vendor belum ada — backup artisan dilewati"
        return
    fi

    run_cmd "Database backup" "$PHP_BIN" artisan app:backup
}

run_database() {
    step_header "Database..."
    check_database_connection
}
