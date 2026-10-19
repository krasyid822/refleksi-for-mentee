#!/usr/bin/env bash
#
# push.sh - Push proyek ke GitHub dan/atau server FTP.
#
# Skrip ini menangani dua tujuan sekaligus:
#   1. GitHub  -> memakai `git push` (aturan file diatur oleh .gitignore,
#                 plus pemeriksaan keamanan bawaan skrip ini).
#   2. Server  -> memakai `git ftp` dengan aturan dari .git-ftp-ignore
#      (FTP)      dan .git-ftp-include.
#
# Pemakaian:
#   ./push.sh                  # commit + push ke GitHub dan FTP
#   ./push.sh github           # hanya ke GitHub
#   ./push.sh ftp              # hanya ke server FTP
#   ./push.sh ftp --init       # unggahan pertama ke server FTP kosong
#   ./push.sh all -m "pesan"   # tentukan pesan commit
#   ./push.sh all --no-commit  # push tanpa membuat commit baru
#   ./push.sh --help           # bantuan
#
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${APP_DIR}/.env"
FTP_IGNORE_FILE=".git-ftp-ignore"
FTP_INCLUDE_FILE=".git-ftp-include"

# ------------------------------------------------------------------
# File yang TIDAK BOLEH dikirim ke GitHub (selain aturan .gitignore).
# Pola memakai glob bash.
# ------------------------------------------------------------------
GITHUB_DENY=(".env" "*.pem" "*.key" "*.ppk" "id_rsa*" "*.p12" "*.keystore")

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
${BOLD}Refleksi for Mentee - push.sh${RESET}

${BOLD}Pemakaian:${RESET}
  ./push.sh [tujuan] [opsi]

${BOLD}Tujuan:${RESET}
  all        Push ke GitHub dan FTP (default)
  github     Hanya push ke GitHub
  ftp        Hanya push ke server FTP

${BOLD}Opsi:${RESET}
  --init         Unggahan pertama ke server FTP kosong (git ftp init)
  --no-commit    Jangan buat commit baru, langsung push
  -m, --message  Pesan commit (default: "Update <tanggal>")
  -h, --help     Tampilkan bantuan ini

${BOLD}Aturan file:${RESET}
  GitHub : .gitignore + pemeriksaan keamanan (.env, *.pem, *.key, dll.)
  FTP    : .git-ftp-ignore (yang dilewati) & .git-ftp-include (yang dipaksa ikut)

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

# ------------------------------------------------------------------
# Pemeriksaan keamanan: file terlarang untuk GitHub
# ------------------------------------------------------------------
matches_deny() {
  local file="$1" pat
  for pat in "${GITHUB_DENY[@]}"; do
    # shellcheck disable=SC2254
    case "$file" in $pat) return 0 ;; esac
  done
  return 1
}

check_github_safety() {
  local offenders=() file

  # a. File yang sudah ter-track di Git
  while IFS= read -r file; do
    [ -z "$file" ] && continue
    if matches_deny "$file"; then offenders+=("$file"); fi
  done < <(git -C "$APP_DIR" ls-files)

  # b. File yang staged (calon commit)
  while IFS= read -r file; do
    [ -z "$file" ] && continue
    if matches_deny "$file"; then offenders+=("$file"); fi
  done < <(git -C "$APP_DIR" diff --cached --name-only)

  # buang duplikat
  if [ "${#offenders[@]}" -gt 0 ]; then
    error "File berikut tidak boleh dikirim ke GitHub:"
    while IFS= read -r file; do
      [ -n "$file" ] && printf '       - %s\n' "$file" >&2
    done < <(printf '%s\n' "${offenders[@]}" | sort -u)
    error "Keluarkan dari Git dengan: git rm --cached <file>"
    return 1
  fi
  return 0
}

# ------------------------------------------------------------------
# Commit
# ------------------------------------------------------------------
commit_changes() {
  [ "$DO_COMMIT" -eq 1 ] || { info "Melewati pembuatan commit (--no-commit)."; return 0; }

  git -C "$APP_DIR" add -A

  if git -C "$APP_DIR" diff --cached --quiet; then
    info "Tidak ada perubahan untuk di-commit."
    return 0
  fi

  local msg="${COMMIT_MSG:-Update $(date '+%Y-%m-%d %H:%M')}"
  git -C "$APP_DIR" commit -q -m "$msg"
  ok "Commit dibuat: ${msg}"
}

# ------------------------------------------------------------------
# Tujuan 1: GitHub
# ------------------------------------------------------------------
push_github() {
  title "Push ke GitHub"

  if ! git -C "$APP_DIR" remote get-url origin >/dev/null 2>&1; then
    error "Remote 'origin' belum dikonfigurasi."
    return 1
  fi

  info "Remote : $(git -C "$APP_DIR" remote get-url origin)"
  info "Branch : $(git -C "$APP_DIR" rev-parse --abbrev-ref HEAD)"

  if git -C "$APP_DIR" rev-parse --abbrev-ref --symbolic-full-name '@{u}' >/dev/null 2>&1; then
    git -C "$APP_DIR" push
  else
    git -C "$APP_DIR" push -u origin HEAD
  fi
  ok "Push ke GitHub selesai."
}

# ------------------------------------------------------------------
# Tujuan 2: server FTP
# ------------------------------------------------------------------
push_ftp() {
  title "Push ke server FTP"

  if ! command -v git-ftp >/dev/null 2>&1; then
    error "git-ftp tidak ditemukan di PATH."
    error "Pasang dulu: https://github.com/git-ftp/git-ftp"
    return 1
  fi
  ok "git-ftp $(git ftp --version 2>/dev/null | awk '{print $3}') terdeteksi."

  load_env || return 1

  local missing=()
  [ -z "${FTP_URL:-}" ]      && missing+=("FTP_URL")
  [ -z "${FTP_USER:-}" ]     && missing+=("FTP_USER")
  [ -z "${FTP_PASSWORD:-}" ] && missing+=("FTP_PASSWORD")
  if [ "${#missing[@]}" -gt 0 ]; then
    error "Variabel berikut belum diisi di .env: ${missing[*]}"
    return 1
  fi
  case "$FTP_URL" in
    *HERE*|*example.com*) warn "FTP_URL masih terlihat placeholder: ${FTP_URL}" ;;
  esac

  # Pastikan aturan file FTP tersedia
  [ -f "${APP_DIR}/${FTP_IGNORE_FILE}" ]  || warn "${FTP_IGNORE_FILE} tidak ada - semua file Git akan diunggah."
  [ -f "${APP_DIR}/${FTP_INCLUDE_FILE}" ] || warn "${FTP_INCLUDE_FILE} tidak ada - file untracked (mis. .env) tidak akan diunggah."

  # git-ftp bekerja pada commit; pastikan tidak ada perubahan tertunda
  if [ -n "$(git -C "$APP_DIR" status --porcelain)" ]; then
    warn "Masih ada perubahan belum di-commit; git-ftp hanya mengunggah isi commit."
  fi

  local action="push"
  [ "$DO_INIT" -eq 1 ] && action="init"

  local FTP_ARGS=(-u "$FTP_USER" -p "$FTP_PASSWORD")
  if [ -n "${FTP_SYNCROOT:-}" ]; then
    FTP_ARGS+=(--syncroot "$FTP_SYNCROOT")
  fi

  info "Aksi   : git ftp ${action}"
  info "Target : ${FTP_URL}"
  info "Commit : $(git -C "$APP_DIR" rev-parse --short HEAD 2>/dev/null || echo '-')"

  if git -C "$APP_DIR" ftp "$action" "${FTP_ARGS[@]}" "$FTP_URL"; then
    ok "Push ke FTP selesai."
  else
    error "Push ke FTP gagal. Periksa kredensial & koneksi di .env."
    return 1
  fi
}

# ==================================================================
# Parsing argumen
# ==================================================================
ACTION="all"
DO_INIT=0
DO_COMMIT=1
COMMIT_MSG=""

while [ "$#" -gt 0 ]; do
  case "$1" in
    all|github|ftp) ACTION="$1" ;;
    --init)         DO_INIT=1 ;;
    --no-commit)    DO_COMMIT=0 ;;
    -m|--message)   COMMIT_MSG="${2:-}"; shift ;;
    -h|--help)      usage; exit 0 ;;
    *) error "Argumen tidak dikenal: $1"; usage; exit 1 ;;
  esac
  shift
done

# Pastikan berada di repo Git
if ! git -C "$APP_DIR" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  error "${APP_DIR} bukan repositori Git."
  exit 1
fi

# Jalankan sesuai tujuan
# Pemeriksaan keamanan & commit dilakukan sekali di sini, lalu push ke tujuan.
check_github_safety
commit_changes

case "$ACTION" in
  github) push_github ;;
  ftp)    push_ftp ;;
  all)
    push_github
    push_ftp
    ;;
esac
