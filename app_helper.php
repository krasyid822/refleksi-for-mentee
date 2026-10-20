<?php
// File: app_helper.php
// Utilitas kecil yang dipakai beberapa fitur: folder cache, baca/tulis JSON,
// dan pembersihan teks sebelum dikirim ke layanan eksternal.

if (!function_exists('app_cache_dir')) {
    /**
     * Folder cache yang bisa ditulis. Di hosting, folder ini dibuat otomatis
     * dan dilindungi .htaccess. Jika tidak bisa ditulis, pakai temp dir.
     */
    function app_cache_dir(): string
    {
        static $dir = null;
        if ($dir !== null) {
            return $dir;
        }

        $candidates = [
            __DIR__ . '/cache',
            rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'refleksi-cache',
        ];

        foreach ($candidates as $candidate) {
            if (!is_dir($candidate)) {
                @mkdir($candidate, 0755, true);
            }
            if (!is_dir($candidate) || !is_writable($candidate)) {
                continue;
            }

            $guard = $candidate . DIRECTORY_SEPARATOR . '.htaccess';
            if (!is_file($guard)) {
                @file_put_contents(
                    $guard,
                    "<IfModule mod_authz_core.c>\n"
                    . "    Require all denied\n"
                    . "</IfModule>\n"
                    . "<IfModule !mod_authz_core.c>\n"
                    . "    Order deny,allow\n"
                    . "    Deny from all\n"
                    . "</IfModule>\n"
                );
            }
            $index = $candidate . DIRECTORY_SEPARATOR . 'index.html';
            if (!is_file($index)) {
                @file_put_contents($index, '');
            }

            $dir = $candidate;
            return $dir;
        }

        $dir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        return $dir;
    }
}

if (!function_exists('app_json_read')) {
    /** Baca JSON dari file; kembalikan $default bila gagal. */
    function app_json_read(string $file, array $default = []): array
    {
        if (!is_file($file) || !is_readable($file)) {
            return $default;
        }
        $raw = @file_get_contents($file);
        if ($raw === false || trim($raw) === '') {
            return $default;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : $default;
    }
}

if (!function_exists('app_json_write')) {
    /** Tulis JSON secara atomik (tulis file sementara lalu rename). */
    function app_json_write(string $file, array $data): bool
    {
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return false;
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            return false;
        }

        $tmp = $file . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
            return false;
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
        @chmod($file, 0644);
        return true;
    }
}

if (!function_exists('app_redact_text')) {
    /**
     * Sensor data pribadi sederhana sebelum teks dikirim ke issue publik.
     * Bukan jaminan anonim, hanya pengurang risiko.
     */
    function app_redact_text(string $text, int $maxLen = 1200): string
    {
        $text = trim($text);

        // Email, nomor telepon/WA, dan URL.
        $text = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email disamarkan]', $text);
        $text = preg_replace('/(?:\+62|62|0)8[1-9][0-9]{6,11}/', '[nomor disamarkan]', $text);
        $text = preg_replace('#https?://\S+#i', '[tautan disamarkan]', $text);

        if (function_exists('mb_substr')) {
            if (mb_strlen($text) > $maxLen) {
                $text = mb_substr($text, 0, $maxLen) . '…';
            }
        } elseif (strlen($text) > $maxLen) {
            $text = substr($text, 0, $maxLen) . '…';
        }

        return $text;
    }
}

if (!function_exists('app_is_same_origin_request')) {
    /** Cek Origin/Referer untuk endpoint yang memakai API key server. */
    function app_is_same_origin_request(): bool
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            return true; // CLI / request tanpa host.
        }

        $candidates = [];
        if (!empty($_SERVER['HTTP_ORIGIN'])) {
            $candidates[] = $_SERVER['HTTP_ORIGIN'];
        }
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $candidates[] = $_SERVER['HTTP_REFERER'];
        }
        if ($candidates === []) {
            return true;
        }

        foreach ($candidates as $url) {
            $parts = parse_url($url);
            if (!is_array($parts) || empty($parts['host'])) {
                continue;
            }
            $port = isset($parts['port']) ? ':' . $parts['port'] : '';
            if (strcasecmp($parts['host'] . $port, $host) === 0) {
                return true;
            }
        }

        return false;
    }
}
