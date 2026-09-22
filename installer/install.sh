#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
INSTALLER_DIR="$SCRIPT_DIR"

cd "$ROOT"

# shellcheck disable=SC1091
source "$INSTALLER_DIR/lib/common.sh"
# shellcheck disable=SC1091
source "$INSTALLER_DIR/lib/requirements.sh"
# shellcheck disable=SC1091
source "$INSTALLER_DIR/lib/environment.sh"
# shellcheck disable=SC1091
source "$INSTALLER_DIR/lib/database.sh"
# shellcheck disable=SC1091
source "$INSTALLER_DIR/lib/laravel.sh"
# shellcheck disable=SC1091
source "$INSTALLER_DIR/lib/frontend.sh"
# shellcheck disable=SC1091
source "$INSTALLER_DIR/lib/services.sh"
# shellcheck disable=SC1091
source "$INSTALLER_DIR/lib/verify.sh"

parse_args "$@"
ensure_log_file
load_installer_conf

log "======== installer start (dry_run=$DRY_RUN force=$FORCE) ========"
log "Root: $ROOT"

banner "APPLICATION INSTALLER"
echo "KlinikDent — Laravel 12 production installer"
echo "Log: $LOG_FILE"

if $DRY_RUN; then
    echo
    echo "Mode: DRY RUN (tidak ada perubahan destruktif)"
fi

run_requirements
run_environment
run_database
install_composer_dependencies
run_laravel_setup
run_frontend
run_services
run_verify

if $DRY_RUN; then
    print_planned
fi

log "======== installer finished ========"
exit 0
