#!/usr/bin/env bash

install_composer_dependencies() {
    step_header "Composer..."
    local extra=()

    if [[ "$(env_get APP_ENV local)" == "production" ]]; then
        extra+=(--no-dev)
    fi

    run_cmd "composer install" $COMPOSER_BIN install --no-interaction --prefer-dist --optimize-autoloader "${extra[@]}"
}

ensure_app_key() {
    local key
    key="$(env_get APP_KEY)"

    if [[ -n "$key" ]]; then
        ok "Generate APP_KEY  (sudah ada)"
        return
    fi

    run_cmd "Generate APP_KEY" "$PHP_BIN" artisan key:generate --no-interaction --force
}

ensure_storage_link() {
    run_cmd "Storage Link" "$PHP_BIN" artisan storage:link --no-interaction
}

run_migrations() {
    step_header "Database migration..."

    if $DRY_RUN; then
        plan "php artisan migrate --force"
        ok "Database Migration  (dry-run)"
        return
    fi

    if ! $FORCE; then
        if ! confirm "Run migrations?" Y; then
            warn "Migration dilewati"
            return
        fi
    fi

    maybe_backup_database
    run_cmd "Database Migration" "$PHP_BIN" artisan migrate --force --no-interaction
}

seed_core() {
    run_cmd "Seeder RolePermission" "$PHP_BIN" artisan db:seed --class=RolePermissionSeeder --force --no-interaction
    run_cmd "Seeder Menu" "$PHP_BIN" artisan db:seed --class=MenuSeeder --force --no-interaction
    run_cmd "Seeder WhatsApp templates" "$PHP_BIN" artisan db:seed --class=WhatsAppTemplateSeeder --force --no-interaction
    run_cmd "Seeder SaaS packages" "$PHP_BIN" artisan db:seed --class=SaasSeeder --force --no-interaction
    run_cmd "Create super admin" "$PHP_BIN" "$INSTALLER_DIR/lib/php/create-super-admin.php"
}

seed_demo() {
    echo
    warn "DatabaseSeeder membuat tenant demo, user demo, dan data operasional."
    warn "UserSeeder memakai updateOrCreate dan dapat mereset password ke ADMIN_PASSWORD."
    warn "RegistrationSeeder tidak idempotent — dapat menambah pasien demo baru."
    echo

    run_cmd "Database Seeder (full demo)" "$PHP_BIN" artisan db:seed --force --no-interaction
}

run_seeders() {
    step_header "Seeder..."

    if $SKIP_SEED; then
        warn "Seeder dilewati (--skip-seed)"
        return
    fi

    local users env default_seed default_prompt
    users="$(database_user_count)"
    env="$(env_get APP_ENV local)"

    if [[ "$users" != "0" && "$users" != "" ]]; then
        default_seed=N
        echo "Database sudah berisi $users user."
        echo "Seeder penuh TIDAK idempotent untuk data operasional."
        default_prompt="Jalankan seeder lagi? (berisiko duplikat/reset password demo)"
    elif [[ "$env" == "production" ]]; then
        default_seed=Y
        default_prompt="Jalankan seeder inti production (roles, menu, paket, super admin)?"
    else
        default_seed=Y
        default_prompt="Jalankan DatabaseSeeder (termasuk data demo)?"
    fi

    if $DRY_RUN; then
        if [[ "$env" == "production" && ( "$users" == "0" || "$users" == "" ) ]]; then
            plan "php artisan db:seed --class=RolePermissionSeeder --force"
            plan "php artisan db:seed --class=MenuSeeder --force"
            plan "php artisan db:seed --class=WhatsAppTemplateSeeder --force"
            plan "php artisan db:seed --class=SaasSeeder --force"
            plan "php installer/lib/php/create-super-admin.php"
        else
            plan "php artisan db:seed --force"
        fi
        ok "Database Seeder  (dry-run)"
        return
    fi

    if $FORCE && [[ "$users" != "0" && "$users" != "" ]]; then
        warn "Seeder dilewati (--force pada database yang sudah berisi data)"
        return
    fi

    if ! confirm "$default_prompt" "$default_seed"; then
        warn "Seeder dilewati"
        return
    fi

    if [[ "$env" == "production" ]]; then
        seed_core
        echo
        warn "Super admin memakai ADMIN_EMAIL / ADMIN_PASSWORD dari .env."
        warn "Ganti password segera setelah login pertama."
        if [[ "$(env_get ADMIN_PASSWORD password)" == "password" ]]; then
            warn "ADMIN_PASSWORD masih 'password' — jangan biarkan di production."
        fi
        return
    fi

    if confirm "Pakai data demo lengkap (tenant A/B + semua role)?" Y; then
        seed_demo
        echo
        warn "Akun demo memakai ADMIN_PASSWORD. Ganti password sebelum production."
        return
    fi

    seed_core
}

cache_application() {
    local env
    env="$(env_get APP_ENV local)"

    run_cmd "optimize:clear" "$PHP_BIN" artisan optimize:clear --no-interaction

    if [[ "$env" != "production" ]]; then
        ok "Config/Route/View cache dilewati (bukan production)"
        return
    fi

    run_cmd "Config Cache" "$PHP_BIN" artisan config:cache --no-interaction
    run_cmd "Route Cache" "$PHP_BIN" artisan route:cache --no-interaction
    run_cmd "View Cache" "$PHP_BIN" artisan view:cache --no-interaction
}

fix_permissions() {
    local user
    user="$(detect_web_user)"

    plan "chown/chmod storage bootstrap/cache (user=$user)"

    if $DRY_RUN; then
        ok "Permissions  (dry-run, user=$user)"
        return
    fi

    chmod -R ug+rwx "$ROOT/storage" "$ROOT/bootstrap/cache" 2>/dev/null || true
    find "$ROOT/storage" "$ROOT/bootstrap/cache" -type d -exec chmod 775 {} + 2>/dev/null || true
    find "$ROOT/storage" "$ROOT/bootstrap/cache" -type f -exec chmod 664 {} + 2>/dev/null || true

    if is_linux && [[ "$(id -u)" -eq 0 ]]; then
        chown -R "$user:$user" "$ROOT/storage" "$ROOT/bootstrap/cache"
        ok "Ownership storage/bootstrap/cache → $user"
    else
        ok "Permission storage/bootstrap/cache (ug+rwx)"
        if is_linux; then
            warn "Jalankan sebagai root untuk chown ke $user jika web server gagal menulis log/upload"
        fi
    fi
}

run_laravel_setup() {
    ensure_app_key
    ensure_storage_link
    run_migrations
    run_seeders
    cache_application
    fix_permissions
}
