<?php
// File: env.php
// Bootstrap aplikasi: memuat konfigurasi (.env / .env.enc), mendefinisikan
// konstanta, dan membuat koneksi database.

// Tolak akses langsung ke file ini dari browser. Include dari file lain tetap
// berjalan normal karena SCRIPT_FILENAME menunjuk ke file pemanggil.
if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME'])) {
    $__self = realpath(__FILE__);
    $__script = realpath((string) $_SERVER['SCRIPT_FILENAME']);
    if ($__self !== false && $__script !== false && $__self === $__script) {
        http_response_code(404);
        exit;
    }
}

// ------------------------------------------------------------------
// Kunci enkripsi .env.enc (diisi oleh let-encrypt.sh).
// Sengaja disimpan sebagai deretan angka, bukan string yang terlihat
// seperti kunci, agar tidak terbaca sebagai kredensial saat inspeksi.
// ------------------------------------------------------------------
/* LET_ENCRYPT_KEY_START */
$GLOBALS['__refleksi_env_key_bytes'] = [
    140, 28, 195, 130, 50, 85, 107, 71,
    162, 118, 174, 52, 179, 137, 207, 117,
    195, 30, 29, 64, 74, 161, 246, 17,
    56, 96, 187, 125, 188, 43, 98, 26,
];
/* LET_ENCRYPT_KEY_END */

if (!function_exists('env_key_bytes')) {
    function env_key_bytes(): string
    {
        $bytes = $GLOBALS['__refleksi_env_key_bytes'] ?? [];
        if (!is_array($bytes) || $bytes === []) {
            return '';
        }
        $clean = [];
        foreach ($bytes as $b) {
            if (is_numeric($b)) {
                $clean[] = ((int) $b) & 0xFF;
            }
        }
        return $clean === [] ? '' : pack('C*', ...$clean);
    }
}

// ------------------------------------------------------------------
// Loader .env
// ------------------------------------------------------------------
if (!function_exists('applyEnvLine')) {
    /**
     * Terapkan satu baris KEY=VALUE ke environment.
     */
    function applyEnvLine(string $line, bool $override = false): void
    {
        $line = trim($line);

        if ($line === '' || $line[0] === '#' || $line[0] === ';') {
            return;
        }

        if (strpos($line, 'export ') === 0) {
            $line = substr($line, 7);
        }

        if (strpos($line, '=') === false) {
            return;
        }

        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
            return;
        }

        $len = strlen($value);
        if ($len >= 2) {
            $first = $value[0];
            $last = $value[$len - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        if (!$override && (getenv($key) !== false || isset($_ENV[$key]) || isset($_SERVER[$key]))) {
            return;
        }

        if (function_exists('putenv')) {
            @putenv($key . '=' . $value);
        }
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

if (!function_exists('loadEnvString')) {
    /**
     * Muat isi .env dari string (dipakai untuk file biasa maupun hasil dekripsi).
     */
    function loadEnvString(string $content, bool $override = false): void
    {
        $lines = preg_split('/\r\n|\r|\n/', $content);
        if (!is_array($lines)) {
            return;
        }
        foreach ($lines as $line) {
            applyEnvLine((string) $line, $override);
        }
    }
}

if (!function_exists('loadEnv')) {
    /**
     * Membaca file .env dan menyuntikkan nilainya ke environment.
     */
    function loadEnv($path, $override = false): bool
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            return false;
        }
        loadEnvString($content, $override);
        return true;
    }
}

if (!function_exists('loadEncryptedEnv')) {
    /**
     * Membaca .env.enc (AES-256-GCM). Format:
     * {"v":1,"alg":"aes-256-gcm","iv":"...","tag":"...","data":"..."}
     */
    function loadEncryptedEnv($path, bool $override = false): bool
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }
        $key = env_key_bytes();
        if ($key === '') {
            error_log('env.enc ada tetapi kunci belum dipasang di env.php.');
            return false;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return false;
        }
        $j = json_decode($raw, true);
        if (!is_array($j) || ($j['alg'] ?? '') !== 'aes-256-gcm') {
            error_log('Format .env.enc tidak dikenali.');
            return false;
        }

        $iv = base64_decode((string) ($j['iv'] ?? ''), true);
        $tag = base64_decode((string) ($j['tag'] ?? ''), true);
        $data = base64_decode((string) ($j['data'] ?? ''), true);
        if ($iv === false || $tag === false || $data === false) {
            error_log('Isi .env.enc rusak.');
            return false;
        }

        $plain = @openssl_decrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            error_log('Gagal mendekripsi .env.enc. Kunci mungkin tidak cocok.');
            return false;
        }

        loadEnvString($plain, $override);
        return true;
    }
}

if (!function_exists('env')) {
    /**
     * Ambil nilai environment, dengan nilai default opsional.
     * String kosong dianggap belum diset.
     */
    function env($key, $default = null)
    {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        return $default;
    }
}

// ------------------------------------------------------------------
// Bootstrap (hanya dijalankan sekali)
// ------------------------------------------------------------------
if (!defined('APP_BOOTSTRAPPED')) {
    define('APP_BOOTSTRAPPED', true);

    // Prioritas: .env lokal (jika ada), lalu .env.enc untuk server.
    // .env.local selalu menimpa keduanya.
    $envLoaded = loadEnv(__DIR__ . '/.env');
    if (!$envLoaded) {
        $envLoaded = loadEncryptedEnv(__DIR__ . '/.env.enc');
    }
    loadEnv(__DIR__ . '/.env.local', true);

    if (!$envLoaded && PHP_SAPI !== 'cli') {
        http_response_code(500);
        exit('Konfigurasi server tidak lengkap.');
    }

    // --- Zona waktu aplikasi ---
    define('APP_TIMEZONE', env('APP_TIMEZONE', 'Asia/Jakarta'));
    date_default_timezone_set(APP_TIMEZONE);

    // --- Database ---
    define('DB_SERVER', env('DB_SERVER', 'localhost'));
    define('DB_USERNAME', env('DB_USERNAME', ''));
    define('DB_PASSWORD', env('DB_PASSWORD', ''));
    define('DB_NAME', env('DB_NAME', ''));

    // --- Gemini ---
    define('GEMINI_API_KEY', env('GEMINI_API_KEY', ''));
    define('GEMINI_MODEL', env('GEMINI_MODEL', 'gemini-1.5-flash-latest'));

    // --- OpenRouter (server-side) ---
    define('OPENROUTER_API_KEY', env('OPENROUTER_API_KEY', ''));
    define('OPENROUTER_MODEL', env('OPENROUTER_MODEL', ''));
    define('OPENROUTER_SITE_URL', env('OPENROUTER_SITE_URL', ''));
    define('OPENROUTER_APP_NAME', env('OPENROUTER_APP_NAME', 'Refleksi Mentee'));

    // --- GitHub issue (untuk skema baru) ---
    // Dukung nama lama GITHUB_FINEGRAINED_TOKEN agar konfigurasi yang sudah
    // ada tetap bekerja tanpa harus mengganti nama variabel.
    define('GITHUB_TOKEN', env('GITHUB_TOKEN', env('GITHUB_FINEGRAINED_TOKEN', '')));
    define('GITHUB_REPO', env('GITHUB_REPO', 'krasyid822/refleksi-for-mentee'));

    // --- Token opsional untuk memaksa refresh kata-kata ---
    define('KATA_KATA_REFRESH_TOKEN', env('KATA_KATA_REFRESH_TOKEN', ''));

    // --- Koneksi database ---
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        die('Koneksi ke database gagal: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');

    // Samakan zona waktu sesi MySQL dengan PHP.
    $offset = date('P');
    @$conn->query("SET time_zone = '" . $conn->real_escape_string($offset) . "'");
}
