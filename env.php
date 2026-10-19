<?php
// File: env.php
// Bootstrap aplikasi: memuat file .env, mendefinisikan konstanta,
// dan membuat koneksi database. Tidak ada dependency eksternal.

// ------------------------------------------------------------------
// Loader .env
// ------------------------------------------------------------------
if (!function_exists('loadEnv')) {
    /**
     * Membaca file .env dan menyuntikkan nilainya ke environment.
     * Variabel environment yang sudah ada TIDAK ditimpa, sehingga
     * pengaturan dari server (Apache/Nginx) tetap diprioritaskan.
     */
    function loadEnv($path)
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            // Lewati baris kosong dan komentar
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }

            // Dukung awalan "export KEY=value"
            if (strpos($line, 'export ') === 0) {
                $line = substr($line, 7);
            }

            if (strpos($line, '=') === false) {
                continue;
            }

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            // Buang tanda kutip pembungkus bila ada
            $len = strlen($value);
            if ($len >= 2) {
                $first = $value[0];
                $last = $value[$len - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            // Jangan timpa environment yang sudah diset sebelumnya
            if (getenv($key) !== false || isset($_ENV[$key]) || isset($_SERVER[$key])) {
                continue;
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

if (!function_exists('env')) {
    /**
     * Ambil nilai environment, dengan nilai default opsional.
     * String kosong dianggap belum diset.
     *
     * Catatan: sebagian hosting (mis. InfinityFree) menonaktifkan putenv()
     * sehingga getenv() tidak melihat nilai dari .env. Karena itu kita
     * membaca $_ENV / $_SERVER sebagai fallback.
     */
    function env($key, $default = null)
    {
        // 1) Environment asli dari server
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        // 2) Nilai yang disuntikkan loadEnv() ke $_ENV / $_SERVER
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
// Bootstrap (hanya dijalankan sekali walau file di-include berkali-kali)
// ------------------------------------------------------------------
if (!defined('APP_BOOTSTRAPPED')) {
    define('APP_BOOTSTRAPPED', true);

    loadEnv(__DIR__ . '/.env');

    // --- Pengaturan Database ---
    define('DB_SERVER', env('DB_SERVER', 'localhost'));
    define('DB_USERNAME', env('DB_USERNAME', ''));
    define('DB_PASSWORD', env('DB_PASSWORD', ''));
    define('DB_NAME', env('DB_NAME', ''));

    // --- Pengaturan Gemini AI ---
    define('GEMINI_API_KEY', env('GEMINI_API_KEY', ''));
    define('GEMINI_MODEL', env('GEMINI_MODEL', 'gemini-1.5-flash-latest'));

    // --- Pengaturan OpenRouter (fitur form psikologi) ---
    define('OPENROUTER_API_KEY', env('OPENROUTER_API_KEY', ''));

    // --- Membuat koneksi ke database ---
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        die("Koneksi ke database gagal: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
}
