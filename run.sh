#!/usr/bin/env bash
#
# run.sh - Menjalankan proyek "Refleksi for Mentee"
#
# Proyek ini hanya butuh server PHP + MySQL. Skrip ini memakai
# server bawaan PHP (php -S) sehingga cocok untuk pengembangan lokal.
#
# Pemakaian:
#   ./run.sh                 # jalankan di http://localhost:8000
#   PORT=9000 ./run.sh       # ganti port
#   HOST=0.0.0.0 ./run.sh    # agar bisa diakses dari jaringan lokal
#   ./run.sh --help          # bantuan
#
set -euo pipefail

# --- Pengaturan (bisa di-override lewat environment variable) ---
HOST="${HOST:-localhost}"
PORT="${PORT:-8000}"
# Direktori tempat run.sh berada (root proyek)
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DOC_ROOT="${APP_DIR}"

# --- Warna output (dimatikan jika bukan TTY) ---
if [ -t 1 ]; then
  RED=$'\033[0;31m'; GREEN=$'\033[0;32m'; YELLOW=$'\033[1;33m'
  BLUE=$'\033[0;34m'; BOLD=$'\033[1m'; RESET=$'\033[0m'
else
  RED=''; GREEN=''; YELLOW=''; BLUE=''; BOLD=''; RESET=''
fi

info()  { printf '%s[INFO]%s %s\n'  "$BLUE"   "$RESET" "$1"; }
ok()    { printf '%s[ OK ]%s %s\n'  "$GREEN"  "$RESET" "$1"; }
warn()  { printf '%s[WARN]%s %s\n'  "$YELLOW" "$RESET" "$1"; }
error() { printf '%s[FAIL]%s %s\n'  "$RED"    "$RESET" "$1" >&2; }

usage() {
  cat <<EOF
${BOLD}Refleksi for Mentee - run.sh${RESET}

Menjalankan proyek memakai server bawaan PHP.

${BOLD}Opsi environment:${RESET}
  HOST   Alamat bind server        (default: localhost)
  PORT   Port server               (default: 8000)
  PHP    Binary PHP yang dipakai   (default: php)

${BOLD}Contoh:${RESET}
  ./run.sh
  PORT=9000 ./run.sh
  HOST=0.0.0.0 PORT=8080 ./run.sh
EOF
}

if [ "${1:-}" = "-h" ] || [ "${1:-}" = "--help" ]; then
  usage
  exit 0
fi

PHP_BIN="${PHP:-php}"

# --- 1. Cek PHP tersedia ---
if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  error "PHP tidak ditemukan. Pasang PHP lalu coba lagi."
  error "Contoh (Debian/Ubuntu): sudo apt install php php-mysqli php-curl"
  exit 1
fi

# --- 2. Cek ekstensi PHP yang dibutuhkan ---
missing_ext=()
for ext in mysqli curl; do
  if ! "$PHP_BIN" -m 2>/dev/null | grep -qi "^${ext}$"; then
    missing_ext+=("$ext")
  fi
done
if [ "${#missing_ext[@]}" -gt 0 ]; then
  error "Ekstensi PHP berikut belum terpasang: ${missing_ext[*]}"
  error "Contoh (Debian/Ubuntu): sudo apt install php-mysqli php-curl"
  exit 1
fi
ok "PHP $("$PHP_BIN" -r 'echo PHP_VERSION;') dengan ekstensi mysqli & curl."

# --- 3. Cek .env sudah dikonfigurasi ---
ENV_FILE="${APP_DIR}/.env"
if [ ! -f "$ENV_FILE" ]; then
  if [ -f "${APP_DIR}/.env.example" ]; then
    warn ".env belum ada. Menyalin dari .env.example..."
    cp "${APP_DIR}/.env.example" "$ENV_FILE"
    warn "Silakan isi nilai pada ${ENV_FILE} lalu jalankan ulang."
  else
    error ".env tidak ditemukan di ${APP_DIR}."
    exit 1
  fi
fi
if grep -q "HOSTNAME-SERVER-HERE\|GEMINI-API-KEY-HERE\|USERNAME-DATABASE-HERE" "$ENV_FILE"; then
  warn ".env masih berisi nilai placeholder."
  warn "Sesuaikan koneksi database & Gemini API key dulu agar aplikasi berjalan normal."
  warn "Lanjut menjalankan server tetap bisa (halaman akan gagal konek DB)."
fi

# --- 4. Cek port sudah dipakai atau belum ---
port_in_use() {
  if command -v ss >/dev/null 2>&1; then
    ss -ltn 2>/dev/null | awk '{print $4}' | grep -qE "[:.]${PORT}\$"
  elif command -v lsof >/dev/null 2>&1; then
    lsof -iTCP:"${PORT}" -sTCP:LISTEN >/dev/null 2>&1
  else
    return 1
  fi
}
if port_in_use; then
  error "Port ${PORT} sedang dipakai proses lain. Set PORT lain, mis. PORT=8001 ./run.sh"
  exit 1
fi

# --- 5. Jalankan server ---
echo
printf '%s%sRefleksi for Mentee%s\n' "$BOLD" "$BLUE" "$RESET"
echo "-----------------------------------------"
info "Direktori : ${DOC_ROOT}"
info "Alamat    : http://${HOST}:${PORT}"
if [ "$HOST" = "0.0.0.0" ]; then
  info "Catatan   : HOST=0.0.0.0 -> akses lewat IP lokal mesin ini."
fi
info "Berhenti  : tekan Ctrl+C"
echo

exec "$PHP_BIN" -S "${HOST}:${PORT}" -t "$DOC_ROOT"
