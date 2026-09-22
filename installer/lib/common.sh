#!/usr/bin/env bash
# Fungsi bersama installer KlinikDent.

if [[ -z "${ROOT:-}" ]]; then
    echo "ROOT is not set" >&2
    exit 1
fi

INSTALLER_DIR="${INSTALLER_DIR:-$ROOT/installer}"
LOG_FILE="${LOG_FILE:-$ROOT/storage/logs/installer.log}"
DRY_RUN=false
FORCE=false
SKIP_SEED=false
SKIP_BUILD=false
SKIP_SERVICES=false
NON_INTERACTIVE=false
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
PLANNED_COMMANDS=()
STEP_CURRENT=0
STEP_TOTAL=9
WEB_USER="${WEB_USER:-}"
QUEUE_NUMPROCS="${QUEUE_NUMPROCS:-1}"
SUPERVISOR_CONF_DIR="${SUPERVISOR_CONF_DIR:-/etc/supervisor/conf.d}"
SUPERVISOR_PROGRAM="${SUPERVISOR_PROGRAM:-klinikdent-worker}"
PHP_MIN_VERSION="${PHP_MIN_VERSION:-8.3}"
NODE_MIN_VERSION="${NODE_MIN_VERSION:-18}"

SECRET_ENV_KEYS_REGEX='(PASSWORD|SECRET|TOKEN|KEY|DSN|PRIVATE)'

if [[ -t 0 && -t 1 ]]; then
    C_RESET='\033[0m'
    C_BOLD='\033[1m'
    C_DIM='\033[2m'
    C_GREEN='\033[32m'
    C_YELLOW='\033[33m'
    C_RED='\033[31m'
    C_CYAN='\033[36m'
else
    C_RESET=''; C_BOLD=''; C_DIM=''; C_GREEN=''; C_YELLOW=''; C_RED=''; C_CYAN=''
fi

is_windows() {
    case "$(uname -s 2>/dev/null || true)" in
        MINGW*|MSYS*|CYGWIN*) return 0 ;;
        *) return 1 ;;
    esac
}

is_linux() {
    [[ "$(uname -s 2>/dev/null || true)" == Linux ]]
}

timestamp() {
    date '+%Y-%m-%d %H:%M:%S'
}

ensure_log_file() {
    local dir
    dir="$(dirname "$LOG_FILE")"
    mkdir -p "$dir" 2>/dev/null || true

    if [[ ! -w "$dir" ]]; then
        LOG_FILE="$INSTALLER_DIR/logs/installer.log"
        mkdir -p "$(dirname "$LOG_FILE")"
    fi

    touch "$LOG_FILE" 2>/dev/null || {
        LOG_FILE="$INSTALLER_DIR/logs/installer.log"
        mkdir -p "$(dirname "$LOG_FILE")"
        touch "$LOG_FILE"
    }
}

log() {
    local msg="$1"
    echo "[$(timestamp)] $msg" >> "$LOG_FILE"
}

log_secret_safe() {
    local msg="$1"
    msg="$(printf '%s' "$msg" | sed -E "s/(${SECRET_ENV_KEYS_REGEX})=([^ ]*)/\\1=********/g")"
    log "$msg"
}

say() {
    echo -e "$*"
}

ok() {
    echo -e "  ${C_GREEN}✓${C_RESET} $*"
}

warn() {
    echo -e "  ${C_YELLOW}⚠${C_RESET} $*"
}

fail() {
    echo -e "  ${C_RED}✗${C_RESET} $*"
}

die() {
    local reason="${1:-Installation stopped.}"
    echo
    echo -e "${C_RED}${C_BOLD}[FAILED]${C_RESET} $reason"
    echo
    echo "Installation stopped."
    echo "Log: $LOG_FILE"
    log "FAILED: $reason"
    exit 1
}

banner() {
    echo
    echo "========================================"
    echo -e " ${C_BOLD}$1${C_RESET}"
    echo "========================================"
}

section() {
    echo
    echo "$1"
    echo "----------------------------------------"
}

step_header() {
    STEP_CURRENT=$((STEP_CURRENT + 1))
    echo
    echo -e "${C_CYAN}${C_BOLD}[$STEP_CURRENT/$STEP_TOTAL] $1${C_RESET}"
}

plan() {
    PLANNED_COMMANDS+=("$*")
}

print_planned() {
    local i=1
    echo
    echo "DRY RUN"
    echo
    echo "The installer would execute:"
    echo
    if [[ ${#PLANNED_COMMANDS[@]} -eq 0 ]]; then
        echo "(no mutating commands)"
    else
        for cmd in "${PLANNED_COMMANDS[@]}"; do
            echo "$i. $cmd"
            i=$((i + 1))
        done
    fi
    echo
    echo "No changes were made."
}

confirm() {
    local prompt="$1"
    local default="${2:-Y}"
    local reply

    if $FORCE || $NON_INTERACTIVE; then
        [[ "$default" =~ ^[Yy]$ ]]
        return $?
    fi

    if [[ ! -t 0 ]]; then
        [[ "$default" =~ ^[Yy]$ ]]
        return $?
    fi

    if [[ "$default" =~ ^[Yy]$ ]]; then
        read -r -p "$prompt [Y/n] " reply || true
        [[ -z "$reply" || "$reply" =~ ^[Yy]$ ]]
    else
        read -r -p "$prompt [y/N] " reply || true
        [[ "$reply" =~ ^[Yy]$ ]]
    fi
}

prompt_value() {
    local label="$1"
    local current="$2"
    local secret="${3:-false}"
    local reply=""
    local shown="$current"

    if $secret && [[ -n "$current" ]]; then
        shown="********"
    fi

    if $FORCE || $NON_INTERACTIVE; then
        printf '%s' "$current"
        return
    fi

    if [[ -n "$shown" ]]; then
        read -r -p "$label [$shown]: " reply || true
    else
        read -r -p "$label: " reply || true
    fi

    if [[ -z "$reply" ]]; then
        printf '%s' "$current"
    else
        printf '%s' "$reply"
    fi
}

version_ge() {
    local have="$1"
    local need="$2"
    [[ "$(printf '%s\n%s\n' "$need" "$have" | sort -V | head -n1)" == "$need" ]]
}

php_version_short() {
    "$PHP_BIN" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;'
}

php_has_ext() {
    "$PHP_BIN" -m 2>/dev/null | grep -qi "^${1}$"
}

env_file() {
    echo "$ROOT/.env"
}

php_helper() {
    "$PHP_BIN" "$INSTALLER_DIR/lib/php/$1" "${@:2}"
}

env_get() {
    local key="$1"
    local default="${2:-}"
    local file
    file="$(env_file)"

    if [[ ! -f "$file" ]]; then
        printf '%s' "$default"
        return
    fi

    "$PHP_BIN" -r '
        require $argv[1];
        $vars = installer_load_env($argv[2]);
        echo installer_env_get($vars, $argv[3], $argv[4]);
    ' "$INSTALLER_DIR/lib/php/env.php" "$file" "$key" "$default"
}

env_set() {
    local key="$1"
    local value="$2"
    php_helper set-env.php "$key" "$value" >/dev/null
}

backup_file() {
    local file="$1"
    local dest="${file}.installer.bak.$(date +%Y%m%d_%H%M%S)"
    cp "$file" "$dest"
    log "Backup $file -> $dest"
    echo "$dest"
}

run_cmd() {
    local title="$1"
    shift
    local start end duration status
    local tmp_out tmp_err
    start="$(date +%s)"
    tmp_out="$(mktemp)"
    tmp_err="$(mktemp)"

    log_secret_safe "Running $title"
    plan "$*"

    if $DRY_RUN; then
        ok "$title  (dry-run)"
        rm -f "$tmp_out" "$tmp_err"
        return 0
    fi

    if "$@" >"$tmp_out" 2>"$tmp_err"; then
        status="SUCCESS"
    else
        status="FAILED"
    fi

    end="$(date +%s)"
    duration=$((end - start))

    {
        echo "[$(timestamp)] $title ($status, ${duration}s)"
        if [[ -s "$tmp_out" ]]; then
            echo "----- stdout -----"
            cat "$tmp_out"
        fi
        if [[ -s "$tmp_err" ]]; then
            echo "----- stderr -----"
            cat "$tmp_err"
        fi
        echo
    } >> "$LOG_FILE"

    if [[ "$status" == "SUCCESS" ]]; then
        ok "$title"
        rm -f "$tmp_out" "$tmp_err"
        return 0
    fi

    fail "$title"
    echo
    echo "Reason:"
    if [[ -s "$tmp_err" ]]; then
        sed 's/\(PASSWORD\|SECRET\|TOKEN\|APP_KEY\)=[^ ]*/\1=********/gI' "$tmp_err" | tail -n 20
    elif [[ -s "$tmp_out" ]]; then
        sed 's/\(PASSWORD\|SECRET\|TOKEN\|APP_KEY\)=[^ ]*/\1=********/gI' "$tmp_out" | tail -n 20
    else
        echo "(no output)"
    fi
    rm -f "$tmp_out" "$tmp_err"
    die "$title"
}

run_cmd_soft() {
    local title="$1"
    shift
    local tmp_out tmp_err
    tmp_out="$(mktemp)"
    tmp_err="$(mktemp)"

    log_secret_safe "Running $title (optional)"
    plan "$*"

    if $DRY_RUN; then
        ok "$title  (dry-run)"
        rm -f "$tmp_out" "$tmp_err"
        return 0
    fi

    if "$@" >"$tmp_out" 2>"$tmp_err"; then
        ok "$title"
        cat "$tmp_out" >> "$LOG_FILE"
        rm -f "$tmp_out" "$tmp_err"
        return 0
    fi

    warn "$title — dilewati"
    {
        echo "[$(timestamp)] $title (SKIPPED/FAILED)"
        cat "$tmp_err"
        echo
    } >> "$LOG_FILE"
    rm -f "$tmp_out" "$tmp_err"
    return 1
}

detect_web_user() {
    if [[ -n "$WEB_USER" ]]; then
        echo "$WEB_USER"
        return
    fi

    local candidate
    for candidate in www-data nginx apache httpd; do
        if id "$candidate" >/dev/null 2>&1; then
            echo "$candidate"
            return
        fi
    done

    id -un 2>/dev/null || echo "$(whoami)"
}

load_installer_conf() {
    local conf="$INSTALLER_DIR/config/installer.conf"
    if [[ -f "$conf" ]]; then
        # shellcheck disable=SC1090
        source "$conf"
        log "Loaded $conf"
    fi
}

usage() {
    cat <<'EOF'
KlinikDent installer

Usage:
  ./installer/install.sh
  ./installer/install.sh --dry-run
  ./installer/install.sh --skip-seed
  ./installer/install.sh --skip-build
  ./installer/install.sh --skip-services
  ./installer/install.sh --force

Flags:
  --dry-run         Deteksi & rencanakan command, tanpa perubahan
  --skip-seed       Lewati database seeder
  --skip-build      Lewati npm ci / npm run build
  --skip-services   Lewati setup queue worker & cron
  --force           Non-interaktif (pakai .env yang ada)
  -h, --help        Tampilkan bantuan
EOF
}

parse_args() {
    while [[ $# -gt 0 ]]; do
        case "$1" in
            --dry-run) DRY_RUN=true ;;
            --skip-seed) SKIP_SEED=true ;;
            --skip-build) SKIP_BUILD=true ;;
            --skip-services) SKIP_SERVICES=true ;;
            --force) FORCE=true; NON_INTERACTIVE=true ;;
            -h|--help) usage; exit 0 ;;
            *)
                echo "Unknown flag: $1" >&2
                usage
                exit 1
                ;;
        esac
        shift
    done
}
