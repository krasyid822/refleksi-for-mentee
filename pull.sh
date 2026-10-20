#!/usr/bin/env bash
#
# pull.sh - Tarik file terbaru dari server FTP ke folder lokal.
#
# Penting: skrip ini HANYA mengunduh/memperbarui file. Ia TIDAK PERNAH
# menghapus file yang ada di lokal. (Tidak memakai opsi --delete pada lftp.)
#
# Pemakaian:
#   ./pull.sh              # unduh file terbaru dari server ke folder proyek
#   ./pull.sh --dry-run    # tampilkan apa yang akan diunduh (tanpa mengunduh)
#   ./pull.sh --with-env   # sertakan .env & .env.enc (default: dilewati)
#   ./pull.sh --help       # bantuan
#
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${APP_DIR}/.env"

# ------------------------------------------------------------------
# Warna & helper output
# ------------------------------------------------------------------
if [ -t 1 ]; then
  RED=$'\033[0;31m'; GREEN=$'\033[0;32m'; YELLOW=$'\033[1;33m'
  BLUE=$'\033[0;34m'; BOLD=$'\033[1m'; RESET=$'\033[0m'
else
  RED=''; GREEN=''; YELLOW=''; BLUE=''; BOLD=''; RESET=''
fi
info()  { printf '%s[INFO]%s %s\n' "$BLUE"   "$RESET" "$1"; }
ok()    { printf '%s[ OK ]%s %s\n' "$GREEN"  "$RESET" "$1"; }
warn()  { printf '%s[WARN]%s %s\n' "$YELLOW" "$RESET" "$1"; }
error() { printf '%s[FAIL]%s %s\n' "$RED"    "$RESET" "$1" >&2; }
title() { printf '\n%s%s%s\n' "$BOLD$BLUE" "$1" "$RESET"; echo "-----------------------------------------"; }

usage() {
  cat <<EOF
${BOLD}Refleksi for Mentee - pull.sh${RESET}

Menarik file terbaru dari server FTP. File yang hanya ada di lokal
TIDAK dihapus (mirror tanpa --delete).

${BOLD}Pemakaian:${RESET}
  ./pull.sh [opsi]

${BOLD}Opsi:${RESET}
  --into DIR     Unduh ke folder DIR (default: folder proyek). Berguna untuk
                 meninjau sebelum menimpa file lokal.
  --dry-run      Tampilkan rencana unduhan tanpa mengunduh
  --with-env     Ikut mengunduh .env dan .env.enc (default: keduanya dilewati)
  --quiet        Kurangi keluaran lftp
  -h, --help     Tampilkan bantuan ini

${BOLD}Konfigurasi FTP (di .env):${RESET}
  FTP_URL, FTP_USER, FTP_PASSWORD, FTP_SYNCROOT
EOF
}

# ------------------------------------------------------------------
# Muat variabel dari .env
# ------------------------------------------------------------------
load_env() {
  if [ ! -f "$ENV_FILE" ]; then
    error ".env tidak ditemukan. Salin dari .env.example lalu isi nilainya:"
    error "  cp .env.example .env"
    return 1
  fi
  while IFS='=' read -r key value; do
    key="$(printf '%s' "$key" | tr -d '[:space:]')"
    [ -z "$key" ] && continue
    case "$key" in \#*|';'*) continue ;; esac
    value="${value%\"}"; value="${value#\"}"
    value="${value%\'}"; value="${value#\'}"
    printf -v "$key" '%s' "$value"
    export "${key?}"
  done < <(grep -vE '^[[:space:]]*(#|;|$)' "$ENV_FILE")
}

# ==================================================================
# Parsing argumen
# ==================================================================
DRY_RUN=0
WITH_ENV=0
VERBOSE="--verbose"
INTO_DIR=""

while [ "$#" -gt 0 ]; do
  case "$1" in
    --dry-run)  DRY_RUN=1 ;;
    --with-env) WITH_ENV=1 ;;
    --into)     INTO_DIR="${2:-}"; shift ;;
    --quiet)    VERBOSE="" ;;
    -h|--help)  usage; exit 0 ;;
    *) error "Argumen tidak dikenal: $1"; usage; exit 1 ;;
  esac
  shift
done

# ------------------------------------------------------------------
# Prasyarat
# ------------------------------------------------------------------
if ! command -v lftp >/dev/null 2>&1; then
  error "lftp tidak ditemukan di PATH (dipakai untuk mengunduh dari FTP)."
  error "Pasang dulu, mis.: sudo apt install lftp"
  exit 1
fi

load_env

missing=()
[ -z "${FTP_URL:-}" ]      && missing+=("FTP_URL")
[ -z "${FTP_USER:-}" ]     && missing+=("FTP_USER")
[ -z "${FTP_PASSWORD:-}" ] && missing+=("FTP_PASSWORD")
if [ "${#missing[@]}" -gt 0 ]; then
  error "Variabel berikut belum diisi di .env: ${missing[*]}"
  exit 1
fi
case "$FTP_URL" in
  *HERE*|*example.com*) warn "FTP_URL masih terlihat placeholder: ${FTP_URL}" ;;
esac

# Folder lokal tujuan (mengikuti FTP_SYNCROOT bila diset)
if [ -n "$INTO_DIR" ]; then
  case "$INTO_DIR" in
    /*) LOCAL_DIR="$INTO_DIR" ;;
    *)  LOCAL_DIR="${APP_DIR}/${INTO_DIR}" ;;
  esac
else
  LOCAL_DIR="$APP_DIR"
  if [ -n "${FTP_SYNCROOT:-}" ]; then
    LOCAL_DIR="${APP_DIR}/${FTP_SYNCROOT}"
  fi
fi
mkdir -p "$LOCAL_DIR"

# ------------------------------------------------------------------
# Daftar file yang dilewati saat menarik.
# Ini HANYA memengaruhi unduhan; file lokal tetap utuh.
# ------------------------------------------------------------------
EXCLUDES=( ".git-ftp.log" )
if [ "$WITH_ENV" -eq 0 ]; then
  EXCLUDES+=( ".env" ".env.enc" )   # jangan timpa konfigurasi lokal
fi

MIRROR_OPTS=( --no-perms --no-symlinks )
[ -n "$VERBOSE" ] && MIRROR_OPTS+=( "$VERBOSE" )
for pat in "${EXCLUDES[@]}"; do
  MIRROR_OPTS+=( -x "$pat" )
done
if [ "$DRY_RUN" -eq 1 ]; then
  MIRROR_OPTS+=( --dry-run )
fi

# Perhatian: TIDAK ada opsi --delete di sini, sehingga file lokal tak terhapus.
LFTP_CMD="set net:max-retries 2; set net:timeout 20; \
set net:reconnect-interval-base 3; \
mirror ${MIRROR_OPTS[*]} . \"${LOCAL_DIR}\"; bye"

title "Tarik dari server FTP"
info "Sumber : ${FTP_URL}"
info "Tujuan : ${LOCAL_DIR}"
info "Mode   : $([ "$DRY_RUN" -eq 1 ] && echo 'dry-run (tanpa perubahan)' || echo 'unduh file terbaru')"
info "Lewati : ${EXCLUDES[*]}"
[ "$WITH_ENV" -eq 1 ] && warn ".env dan .env.enc akan ikut diunduh dan dapat menimpa file lokal."

if lftp -u "$FTP_USER","$FTP_PASSWORD" "$FTP_URL" -e "$LFTP_CMD"; then
  echo
  ok "Selesai.$([ "$DRY_RUN" -eq 1 ] && echo ' (dry-run, tidak ada file yang diubah)')"
else
  error "Gagal menarik file dari FTP. Periksa kredensial & koneksi di .env."
  exit 1
fi

# Tampilkan ringkasan perubahan pada Git (bila ini repo Git)
if [ "$DRY_RUN" -eq 0 ] && git -C "$APP_DIR" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  if [ -n "$(git -C "$APP_DIR" status --porcelain)" ]; then
    echo
    info "Perubahan lokal setelah pull (git status):"
    git -C "$APP_DIR" status --short
  else
    info "Tidak ada perubahan pada file yang dilacak Git."
  fi
fi
