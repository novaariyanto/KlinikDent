#!/usr/bin/env bash

check_mark() {
    if [[ "$1" == "ok" ]]; then
        echo "✓"
    elif [[ "$1" == "warn" ]]; then
        echo "⚠"
    else
        echo "✗"
    fi
}

verify_app_key() {
    local key
    key="$(env_get APP_KEY)"
    if [[ -n "$key" ]]; then
        echo ok
    else
        echo fail
    fi
}

verify_database() {
    if php_helper check-db.php ping >/dev/null 2>&1; then
        echo ok
    else
        echo fail
    fi
}

verify_migrations() {
    if database_has_migrations; then
        echo ok
    else
        echo fail
    fi
}

verify_seeder() {
    local users
    users="$(database_user_count)"
    if [[ "$users" != "0" && "$users" != "" ]]; then
        echo ok
    elif $SKIP_SEED; then
        echo warn
    else
        echo warn
    fi
}

verify_storage() {
    local dirs=(
        "$ROOT/storage/app/private"
        "$ROOT/storage/app/public"
        "$ROOT/storage/logs"
        "$ROOT/bootstrap/cache"
    )
    local dir
    for dir in "${dirs[@]}"; do
        [[ -w "$dir" ]] || { echo fail; return; }
    done

    if [[ -L "$ROOT/public/storage" || -d "$ROOT/public/storage" ]]; then
        echo ok
        return
    fi

    echo warn
}

verify_cache() {
    if [[ "$(env_get APP_ENV local)" != "production" ]]; then
        echo ok
        return
    fi

    if [[ -f "$ROOT/bootstrap/cache/config.php" && -f "$ROOT/bootstrap/cache/routes-v7.php" ]]; then
        echo ok
    elif [[ -f "$ROOT/bootstrap/cache/config.php" ]]; then
        echo ok
    else
        echo warn
    fi
}

verify_frontend() {
    if [[ -s "$ROOT/public/build/manifest.json" ]]; then
        echo ok
    elif $SKIP_BUILD; then
        echo warn
    else
        echo fail
    fi
}

verify_queue() {
    if [[ "$(env_get QUEUE_CONNECTION database)" == "sync" ]]; then
        echo warn
        return
    fi

    if [[ "$(php_helper check-db.php table jobs 2>/dev/null || echo no)" == "yes" ]]; then
        echo ok
        return
    fi

    echo warn
}

verify_scheduler() {
    if ! command -v crontab >/dev/null 2>&1; then
        echo warn
        return
    fi

    if crontab -l 2>/dev/null | grep -F "$ROOT" | grep -q 'artisan schedule:run'; then
        echo ok
    else
        echo warn
    fi
}

verify_health_endpoint() {
    local url
    url="$(env_get APP_URL | sed 's:/*$::')"

    if [[ -z "$url" ]]; then
        echo warn
        return
    fi

    if ! command -v curl >/dev/null 2>&1; then
        echo warn
        return
    fi

    if curl -fsS --max-time 5 "$url/up" >/dev/null 2>&1; then
        echo ok
        return
    fi

    echo warn
}

print_external_services() {
    local mail midtrans wa satusehat bpjs redis_needed

    mail="$(env_get MAIL_MAILER log)"
    midtrans="$(env_get MIDTRANS_ENABLED false)"
    wa="$(env_get WHATSAPP_API_BASE_URL https://whatscrm.id)"
    satusehat="$(env_get SATUSEHAT_FAKE true)"
    bpjs="$(env_get BPJS_FAKE true)"
    redis_needed=false

    if [[ "$(env_get QUEUE_CONNECTION)" == "redis" || "$(env_get CACHE_STORE)" == "redis" ]]; then
        redis_needed=true
    fi

    section "Service       Status"
    echo "Database      $(check_mark "$(verify_database)") Connected"
    if $redis_needed; then
        echo "Redis         ⚠ Driver redis dipilih — pastikan Redis berjalan"
    else
        echo "Redis         ⚠ Tidak dipakai (cache/queue/session = database)"
    fi

    if [[ "$mail" == "log" || "$mail" == "array" ]]; then
        echo "Mail          ⚠ Not configured (MAIL_MAILER=$mail)"
    else
        echo "Mail          ⚠ Configuration detected ($mail) — SMTP juga bisa diisi di Settings"
    fi

    if [[ "$midtrans" == "true" ]]; then
        echo "Midtrans      ⚠ Enabled (fallback .env; kredensial juga di modul Payment Gateway)"
    else
        echo "Midtrans      ⚠ Optional (MIDTRANS_ENABLED=false)"
    fi

    echo "WhatsApp      ⚠ Optional (API key per klinik, base $wa)"
    echo "SATUSEHAT     ⚠ Per klinik (SATUSEHAT_FAKE=$satusehat)"
    echo "BPJS          ⚠ Per klinik (BPJS_FAKE=$bpjs)"
    echo "Realtime      ⚠ Not required (BROADCAST_CONNECTION=log)"
    echo "Sentry        ⚠ Tidak terpasang di composer.json"
}

run_production_check() {
    if [[ "$(env_get APP_ENV local)" != "production" ]]; then
        return
    fi

    if $DRY_RUN; then
        plan "php artisan app:production-check"
        return
    fi

    echo
    run_cmd_soft "Production checklist" "$PHP_BIN" artisan app:production-check || true
}

run_verify() {
    step_header "Verification..."

    if $DRY_RUN; then
        print_external_services
        ok "Application ready  (dry-run)"
        return
    fi

    local k_app k_db k_mig k_seed k_store k_cache k_fe k_queue k_sched k_apphttp
    k_app="$(verify_app_key)"
    k_db="$(verify_database)"
    k_mig="$(verify_migrations)"
    k_seed="$(verify_seeder)"
    k_store="$(verify_storage)"
    k_cache="$(verify_cache)"
    k_fe="$(verify_frontend)"
    k_queue="$(verify_queue)"
    k_sched="$(verify_scheduler)"
    k_apphttp="$(verify_health_endpoint)"

    print_external_services

    banner "Installation Verification"
    echo
    printf "APP_KEY                  %s\n" "$(check_mark "$k_app")"
    printf "Database                 %s\n" "$(check_mark "$k_db")"
    printf "Migrations               %s\n" "$(check_mark "$k_mig")"
    printf "Seeder                   %s\n" "$(check_mark "$k_seed")"
    printf "Storage                  %s\n" "$(check_mark "$k_store")"
    printf "Cache                    %s\n" "$(check_mark "$k_cache")"
    printf "Frontend Build           %s\n" "$(check_mark "$k_fe")"
    printf "Queue                    %s\n" "$(check_mark "$k_queue")"
    printf "Scheduler                %s\n" "$(check_mark "$k_sched")"
    printf "Realtime                 %s  (tidak dipakai)\n" "—"
    printf "Application              %s  (/up)\n" "$(check_mark "$k_apphttp")"
    echo

    run_production_check

    if [[ "$k_app" != "ok" || "$k_db" != "ok" || "$k_mig" != "ok" ]]; then
        die "ERROR: Health check gagal. Lihat log installer."
    fi

    if [[ "$k_fe" != "ok" ]] && ! $SKIP_BUILD; then
        die "ERROR: Frontend build belum tersedia. Lihat log installer."
    fi

    banner "INSTALLATION SUCCESSFUL"
    echo
    echo "Document root web server: $ROOT/public"
    echo "Login Super Admin       : $(env_get ADMIN_EMAIL)"
    echo "Ganti password default segera setelah login."
    echo
    echo "Log: $LOG_FILE"
}
