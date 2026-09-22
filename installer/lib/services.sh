#!/usr/bin/env bash

supervisor_conf_path() {
    echo "$SUPERVISOR_CONF_DIR/${SUPERVISOR_PROGRAM}.conf"
}

render_supervisor_conf() {
    local dest="$1"
    local php artisan user logfile queue
    php="$(command -v "$PHP_BIN" || echo /usr/bin/php)"
    artisan="$ROOT/artisan"
    user="$(detect_web_user)"
    logfile="$ROOT/storage/logs/worker.log"
    queue="$(env_get QUEUE_CONNECTION database)"

    sed \
        -e "s|{{PROGRAM}}|$SUPERVISOR_PROGRAM|g" \
        -e "s|{{PHP}}|$php|g" \
        -e "s|{{ARTISAN}}|$artisan|g" \
        -e "s|{{QUEUE_CONNECTION}}|$queue|g" \
        -e "s|{{WEB_USER}}|$user|g" \
        -e "s|{{NUMPROCS}}|$QUEUE_NUMPROCS|g" \
        -e "s|{{LOG_FILE}}|$logfile|g" \
        "$INSTALLER_DIR/templates/supervisor-worker.conf" > "$dest"
}

install_supervisor_worker() {
    local generated="$ROOT/storage/app/supervisor-${SUPERVISOR_PROGRAM}.conf"
    local target
    target="$(supervisor_conf_path)"

    mkdir -p "$(dirname "$generated")"
    render_supervisor_conf "$generated"
    plan "Supervisor worker: $target"

    if $DRY_RUN; then
        ok "Queue worker config  (dry-run)"
        return
    fi

    ok "Template worker: $generated"

    if ! is_linux; then
        warn "Supervisor hanya diinstal otomatis di Linux VPS"
        return
    fi

    if [[ ! -d "$SUPERVISOR_CONF_DIR" ]]; then
        warn "Direktori Supervisor tidak ada ($SUPERVISOR_CONF_DIR)"
        echo "  Salin $generated ke konfigurasi Supervisor server."
        return
    fi

    if [[ -f "$target" ]]; then
        ok "Supervisor config sudah ada: $target"
        return
    fi

    if [[ "$(id -u)" -ne 0 ]]; then
        warn "Butuh root untuk menulis $target"
        echo "  sudo cp $generated $target && sudo supervisorctl reread && sudo supervisorctl update"
        return
    fi

    cp "$generated" "$target"
    if command -v supervisorctl >/dev/null 2>&1; then
        supervisorctl reread >/dev/null
        supervisorctl update >/dev/null
        supervisorctl start "${SUPERVISOR_PROGRAM}:*" >/dev/null 2>&1 || true
        ok "Supervisor worker aktif"
    else
        ok "Supervisor config ditulis — jalankan supervisorctl update"
    fi
}

cron_line() {
    local php
    php="$(command -v "$PHP_BIN" || echo php)"
    echo "* * * * * cd $ROOT && $php artisan schedule:run >> /dev/null 2>&1"
}

install_scheduler_cron() {
    local line existing
    line="$(cron_line)"
    plan "cron: $line"

    if $DRY_RUN; then
        ok "Scheduler cron  (dry-run)"
        return
    fi

    if ! is_linux; then
        warn "Cron tidak dipasang otomatis di OS ini"
        echo "  Tambahkan: $line"
        return
    fi

    if ! command -v crontab >/dev/null 2>&1; then
        warn "crontab tidak tersedia"
        echo "  Tambahkan: $line"
        return
    fi

    existing="$(crontab -l 2>/dev/null || true)"
    if printf '%s\n' "$existing" | grep -F "$ROOT" | grep -q 'artisan schedule:run'; then
        ok "Cron scheduler sudah ada"
        return
    fi

    {
        printf '%s\n' "$existing"
        echo "$line"
    } | grep -v '^$' | crontab - || {
        warn "Gagal menulis crontab. Tambahkan secara manual:"
        echo "  $line"
        return
    }
    ok "Cron scheduler ditambahkan"
}

print_queue_status() {
    local queue
    queue="$(env_get QUEUE_CONNECTION database)"

    section "Queue System"
    echo "Driver: $queue"
    echo "Worker: REQUIRED"
    echo
    echo "Job yang memakai antrian:"
    echo "  - SendWhatsAppTemplateJob"
    echo "  - SyncSatuSehatEncounter"
    echo "  - CheckBpjsMembership"
}

print_schedule_status() {
    section "Scheduler"
    echo "REQUIRED  (routes/console.php)"
    echo "  - saas:expire-subscriptions   daily 01:15"
    echo "  - app:backup                  daily 02:30"
    echo "  - whatsapp:dispatch           every minute"
}

run_services() {
    step_header "Queue & scheduler..."
    print_queue_status
    print_schedule_status

    if $SKIP_SERVICES; then
        warn "Service setup dilewati (--skip-services)"
        echo "  Template worker: $INSTALLER_DIR/templates/supervisor-worker.conf"
        echo "  Cron: $(cron_line)"
        return
    fi

    if $DRY_RUN || $FORCE; then
        install_supervisor_worker
        install_scheduler_cron
        return
    fi

    if confirm "Pasang queue worker (Supervisor)?" Y; then
        install_supervisor_worker
    else
        warn "Queue worker tidak dipasang — job WhatsApp/SATUSEHAT/BPJS tidak terkirim"
    fi

    if confirm "Pasang cron scheduler?" Y; then
        install_scheduler_cron
    else
        warn "Scheduler tidak dipasang — backup harian & kedaluwarsa langganan tidak jalan"
    fi
}
