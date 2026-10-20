#!/usr/bin/env bash
#
# let-encrypt.sh - Enkripsi .env menjadi .env.enc dan pasang kunci ke env.php.
#
# Pemakaian:
#   ./let-encrypt.sh            # buat/perbarui .env.enc, kunci lama dipakai
#   ./let-encrypt.sh --rotate   # buat kunci baru + .env.enc baru
#   ./let-encrypt.sh --check    # verifikasi .env.enc bisa didekripsi
#
# Catatan keamanan:
#   Ini adalah lapisan obfuscation/defense-in-depth. Jika penyerang bisa
#   membaca env.php DAN .env.enc, kunci tetap bisa direkonstruksi. Karena itu
#   .htaccess juga menolak akses langsung ke file konfigurasi.
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${APP_DIR}/.env"
ENC_FILE="${APP_DIR}/.env.enc"
PHP_FILE="${APP_DIR}/env.php"
ROTATE=0
CHECK_ONLY=0

usage() {
  cat <<'EOF'
Penggunaan: ./let-encrypt.sh [opsi]

  --rotate   Buat kunci enkripsi baru (semua .env.enc lama tidak bisa dibuka
             dengan kunci sebelumnya).
  --check    Hanya verifikasi .env.enc terhadap kunci di env.php.
  -h, --help Tampilkan bantuan ini.
EOF
}

while [ "$#" -gt 0 ]; do
  case "$1" in
    --rotate) ROTATE=1 ;;
    --check)  CHECK_ONLY=1 ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Opsi tidak dikenal: $1" >&2; usage; exit 1 ;;
  esac
  shift
done

if ! command -v php >/dev/null 2>&1; then
  echo "PHP CLI tidak ditemukan." >&2
  exit 1
fi

if [ "$CHECK_ONLY" -eq 0 ] && [ ! -f "$ENV_FILE" ]; then
  echo "File .env tidak ditemukan di ${ENV_FILE}" >&2
  exit 1
fi

if [ ! -f "$PHP_FILE" ]; then
  echo "File env.php tidak ditemukan di ${PHP_FILE}" >&2
  exit 1
fi

export ENV_FILE ENC_FILE PHP_FILE ROTATE CHECK_ONLY

php <<'PHP'
<?php
$envFile   = (string) getenv('ENV_FILE');
$encFile   = (string) getenv('ENC_FILE');
$phpFile   = (string) getenv('PHP_FILE');
$rotate    = getenv('ROTATE') === '1';
$checkOnly = getenv('CHECK_ONLY') === '1';

function fail(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

if (!extension_loaded('openssl')) {
    fail('Ekstensi OpenSSL PHP tidak tersedia.');
}

$php = @file_get_contents($phpFile);
if ($php === false) {
    fail('Tidak bisa membaca env.php.');
}

$bytes = [];
$blockPattern = '/\/\* LET_ENCRYPT_KEY_START \*\/.*?\/\* LET_ENCRYPT_KEY_END \*\//s';
if (preg_match($blockPattern, $php, $blockMatch)) {
    if (preg_match('/\$GLOBALS\[\'__refleksi_env_key_bytes\'\]\s*=\s*\[(.*?)\]\s*;/s', $blockMatch[0], $keyMatch)) {
        // Array PHP boleh memiliki trailing comma (bukan JSON valid),
        // jadi ambil angka saja.
        if (preg_match_all('/\d+/', $keyMatch[1], $numberMatch)) {
            foreach ($numberMatch[0] as $v) {
                $bytes[] = ((int) $v) & 0xFF;
            }
        }
    }
}

if ($rotate || count($bytes) !== 32) {
    $bytes = array_values(unpack('C*', random_bytes(32)));
}

if (count($bytes) !== 32) {
    fail('Kunci harus 32 byte.');
}

$key = pack('C*', ...$bytes);

if ($checkOnly) {
    if (!is_file($encFile)) {
        fail('.env.enc belum ada.');
    }
    $j = json_decode((string) file_get_contents($encFile), true);
    if (!is_array($j)) {
        fail('Format .env.enc tidak valid.');
    }
    $iv = base64_decode((string) ($j['iv'] ?? ''), true);
    $tag = base64_decode((string) ($j['tag'] ?? ''), true);
    $data = base64_decode((string) ($j['data'] ?? ''), true);
    $plain = openssl_decrypt((string) $data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, (string) $iv, (string) $tag);
    if ($plain === false) {
        fail('Gagal mendekripsi .env.enc dengan kunci di env.php.');
    }
    echo "OK: .env.enc dapat didekripsi dengan kunci di env.php (" . strlen($plain) . " byte).\n";
    exit(0);
}

// --- Patch kunci ke env.php (deretan angka, bukan string kunci) ---
$chunks = array_chunk($bytes, 8);
$numberLines = [];
foreach ($chunks as $chunk) {
    $numberLines[] = '    ' . implode(', ', $chunk) . ',';
}
$newBlock = "/* LET_ENCRYPT_KEY_START */\n"
    . "\$GLOBALS['__refleksi_env_key_bytes'] = [\n"
    . implode("\n", $numberLines) . "\n"
    . "];\n"
    . "/* LET_ENCRYPT_KEY_END */";

$patched = preg_replace($blockPattern, $newBlock, $php, 1, $count);
if ($patched === null || $count !== 1) {
    fail('Penanda kunci di env.php tidak ditemukan. Periksa file env.php.');
}

$tmpPhp = $phpFile . '.tmp.' . getmypid();
if (file_put_contents($tmpPhp, $patched, LOCK_EX) === false || !rename($tmpPhp, $phpFile)) {
    @unlink($tmpPhp);
    fail('Gagal menulis env.php.');
}
@chmod($phpFile, 0644);

// --- Enkripsi seluruh isi .env ---
$plain = @file_get_contents($envFile);
if ($plain === false) {
    fail('Tidak bisa membaca .env.');
}

$iv = random_bytes(12);
$tag = '';
$cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
if ($cipher === false || strlen($tag) !== 16) {
    fail('Gagal mengenkripsi .env.');
}

$payload = [
    'v'          => 1,
    'alg'        => 'aes-256-gcm',
    'created_at' => date('c'),
    'iv'         => base64_encode($iv),
    'tag'        => base64_encode($tag),
    'data'       => base64_encode($cipher),
];

$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    fail('Gagal menyusun .env.enc.');
}

$tmpEnc = $encFile . '.tmp.' . getmypid();
if (file_put_contents($tmpEnc, $json . "\n", LOCK_EX) === false || !rename($tmpEnc, $encFile)) {
    @unlink($tmpEnc);
    fail('Gagal menulis .env.enc.');
}
@chmod($encFile, 0600);

// Verifikasi cepat.
$dec = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
if ($dec !== $plain) {
    fail('Verifikasi enkripsi gagal.');
}

$keyNote = $rotate ? 'kunci baru' : 'kunci di env.php';
echo "OK: .env.enc dibuat (" . strlen($plain) . " byte) memakai {$keyNote}.\n";
PHP

chmod 600 "$ENC_FILE" 2>/dev/null || true
echo "Langkah berikutnya: ./push.sh ftp   (atau ./push.sh all)"
