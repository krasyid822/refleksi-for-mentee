<?php
// File: openrouter_helper.php
// Koneksi OpenRouter sisi server. Kunci API tidak pernah dikirim ke browser.
// Pola retry/fallback mengikuti pendekatan pada .contekan/_admin.php:
// coba beberapa model gratis, dukung response_format JSON, lalu fallback ke
// parsing JSON manual bila model tidak mendukung mode JSON.

if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/env.php';
}
require_once __DIR__ . '/app_helper.php';

if (!function_exists('openrouter_api_key')) {
    function openrouter_api_key(): string
    {
        return defined('OPENROUTER_API_KEY') ? trim((string) OPENROUTER_API_KEY) : '';
    }
}

if (!function_exists('openrouter_base_headers')) {
    function openrouter_base_headers(bool $withAuth = true): array
    {
        $site = defined('OPENROUTER_SITE_URL') && OPENROUTER_SITE_URL !== ''
            ? OPENROUTER_SITE_URL
            : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $title = defined('OPENROUTER_APP_NAME') && OPENROUTER_APP_NAME !== ''
            ? OPENROUTER_APP_NAME
            : 'Refleksi Mentee';

        $headers = [
            'Accept: application/json',
            'HTTP-Referer: ' . $site,
            'X-Title: ' . $title,
        ];
        if ($withAuth) {
            $headers[] = 'Authorization: Bearer ' . openrouter_api_key();
        }
        return $headers;
    }
}

if (!function_exists('openrouter_http')) {
    /**
     * Request HTTP sederhana. Mengembalikan ok/code/body/error.
     */
    function openrouter_http(string $url, string $method = 'GET', array $headers = [], ?string $body = null, int $timeout = 60): array
    {
        $ch = curl_init();
        $opts = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => 'refleksi-for-mentee/1.0',
        ];
        if (strtoupper($method) === 'POST') {
            $opts[CURLOPT_POST] = true;
            if ($body !== null) {
                $opts[CURLOPT_POSTFIELDS] = $body;
            }
        }
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        return [
            'ok'    => $resp !== false && $code >= 200 && $code < 300,
            'code'  => $code,
            'body'  => $resp === false ? '' : $resp,
            'error' => $err,
        ];
    }
}

if (!function_exists('openrouter_models')) {
    /**
     * Daftar model OpenRouter dengan cache 6 jam.
     * @return array{ok:bool,models:array,error?:string,cached?:bool}
     */
    function openrouter_models(bool $force = false): array
    {
        $cacheFile = app_cache_dir() . DIRECTORY_SEPARATOR . 'openrouter_models.json';
        $cached = app_json_read($cacheFile, []);
        $ttl = 6 * 3600;

        if (!$force && isset($cached['fetched'], $cached['models']) && (time() - (int) $cached['fetched']) < $ttl) {
            return ['ok' => true, 'models' => $cached['models'], 'cached' => true];
        }

        $r = openrouter_http('https://openrouter.ai/api/v1/models', 'GET', openrouter_base_headers(false), null, 30);
        if (!$r['ok']) {
            if (!empty($cached['models'])) {
                return ['ok' => true, 'models' => $cached['models'], 'cached' => true, 'warning' => 'Gagal memperbarui daftar model.'];
            }
            $suffix = $r['code'] ? ' (HTTP ' . $r['code'] . ')' : '';
            return ['ok' => false, 'models' => [], 'error' => 'Gagal mengambil daftar model OpenRouter' . $suffix . '.'];
        }

        $data = json_decode($r['body'], true);
        if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
            if (!empty($cached['models'])) {
                return ['ok' => true, 'models' => $cached['models'], 'cached' => true, 'warning' => 'Format daftar model tidak dikenali.'];
            }
            return ['ok' => false, 'models' => [], 'error' => 'Format daftar model OpenRouter tidak dikenali.'];
        }

        $models = [];
        foreach ($data['data'] as $m) {
            if (!is_array($m) || empty($m['id'])) {
                continue;
            }
            $pricing = is_array($m['pricing'] ?? null) ? $m['pricing'] : [];
            $prompt = (string) ($pricing['prompt'] ?? '');
            $completion = (string) ($pricing['completion'] ?? '');
            $id = (string) $m['id'];
            $models[] = [
                'id'         => $id,
                'name'       => (string) ($m['name'] ?? $id),
                'context'    => (int) ($m['context_length'] ?? 0),
                'prompt'     => $prompt,
                'completion' => $completion,
                'free'       => ($prompt === '0' && $completion === '0') || str_ends_with($id, ':free'),
            ];
        }

        usort($models, static function (array $a, array $b): int {
            return ($b['free'] <=> $a['free'])
                ?: ($b['context'] <=> $a['context'])
                ?: strcmp($a['id'], $b['id']);
        });

        app_json_write($cacheFile, ['fetched' => time(), 'models' => $models]);
        return ['ok' => true, 'models' => $models, 'cached' => false];
    }
}

if (!function_exists('openrouter_free_models')) {
    /** @return array<int,array{id:string,context:int}> */
    function openrouter_free_models(bool $force = false): array
    {
        $r = openrouter_models($force);
        if (!$r['ok']) {
            return [];
        }
        $free = array_values(array_filter($r['models'], static fn($m) => !empty($m['free']) && str_ends_with((string) $m['id'], ':free')));
        usort($free, static fn($a, $b) => ($b['context'] <=> $a['context']) ?: strcmp($a['id'], $b['id']));
        return $free;
    }
}

if (!function_exists('openrouter_chat')) {
    /**
     * Satu panggilan chat completion.
     * @return array{ok:bool,text?:string,error?:string,code?:int,raw?:string}
     */
    function openrouter_chat(string $model, string $system, string $user, bool $json = true, float $temperature = 0.4, int $timeout = 60): array
    {
        $key = openrouter_api_key();
        if ($key === '') {
            return ['ok' => false, 'error' => 'Kunci OpenRouter belum diatur di .env.'];
        }

        $payload = [
            'model'       => $model,
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'temperature' => $temperature,
        ];
        if ($json) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $headers = openrouter_base_headers(true);
        $headers[] = 'Content-Type: application/json';
        $r = openrouter_http(
            'https://openrouter.ai/api/v1/chat/completions',
            'POST',
            $headers,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $timeout
        );

        if (!$r['ok']) {
            $msg = $r['code'] ? 'HTTP ' . $r['code'] : 'Koneksi gagal';
            if ($r['body'] !== '') {
                $short = preg_replace('/\s+/', ' ', $r['body']);
                $msg .= ': ' . (function_exists('mb_substr') ? mb_substr($short, 0, 240) : substr($short, 0, 240));
            } elseif ($r['error'] !== '') {
                $msg .= ': ' . $r['error'];
            }
            return ['ok' => false, 'error' => $msg, 'code' => $r['code']];
        }

        $d = json_decode($r['body'], true);
        $text = $d['choices'][0]['message']['content'] ?? null;
        if (!is_string($text) || trim($text) === '') {
            return ['ok' => false, 'error' => 'Respons model kosong atau tidak berisi konten.', 'raw' => $r['body']];
        }

        return ['ok' => true, 'text' => trim($text)];
    }
}

if (!function_exists('openrouter_extract_json')) {
    /**
     * Ambil objek/array JSON pertama dari teks model, termasuk teks pembungkus.
     */
    function openrouter_extract_json(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        $direct = json_decode($text, true);
        if (is_array($direct)) {
            return $direct;
        }

        // Buang code fence ```json ... ```
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);

        foreach (['{', '['] as $open) {
            $start = strpos($text, $open);
            if ($start === false) {
                continue;
            }
            $close = $open === '{' ? '}' : ']';
            $depth = 0;
            $inStr = false;
            $esc = false;
            $len = strlen($text);
            for ($i = $start; $i < $len; $i++) {
                $ch = $text[$i];
                if ($inStr) {
                    if ($esc) {
                        $esc = false;
                    } elseif ($ch === '\\') {
                        $esc = true;
                    } elseif ($ch === '"') {
                        $inStr = false;
                    }
                    continue;
                }
                if ($ch === '"') {
                    $inStr = true;
                } elseif ($ch === $open) {
                    $depth++;
                } elseif ($ch === $close) {
                    $depth--;
                    if ($depth === 0) {
                        $candidate = substr($text, $start, $i - $start + 1);
                        $decoded = json_decode($candidate, true);
                        if (is_array($decoded)) {
                            return $decoded;
                        }
                        // Coba perbaiki koma berlebih.
                        $fixed = preg_replace('/,\s*([}\]])/', '$1', $candidate);
                        $decoded = json_decode($fixed, true);
                        if (is_array($decoded)) {
                            return $decoded;
                        }
                        break;
                    }
                }
            }
        }

        return null;
    }
}

if (!function_exists('openrouter_is_skip_error')) {
    /** Error yang tidak akan berubah bila dicoba ulang di model yang sama. */
    function openrouter_is_skip_error(string $error): bool
    {
        $e = strtolower($error);
        foreach (['agentic harness', 'key limit exceeded', 'daily limit', 'rate limit', 'quota', 'insufficient', 'unauthorized', 'invalid api key', 'user not found'] as $needle) {
            if (str_contains($e, $needle)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('openrouter_is_auth_error')) {
    function openrouter_is_auth_error(string $error): bool
    {
        $e = strtolower($error);
        foreach (['user not found', 'unauthorized', 'invalid api key', 'authentication', 'http 401'] as $needle) {
            if (str_contains($e, $needle)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('openrouter_try_models')) {
    /**
     * Coba model pilihan lalu beberapa model gratis lain.
     * @return array{ok:bool,text?:string,model?:string,tried:array,error?:string}
     */
    function openrouter_try_models(string $system, string $user, ?string $preferred = null, bool $json = true, int $maxModels = 8): array
    {
        $key = openrouter_api_key();
        if ($key === '') {
            return ['ok' => false, 'error' => 'Kunci OpenRouter belum diatur di .env.', 'tried' => []];
        }

        $free = openrouter_free_models();
        if ($free === []) {
            return ['ok' => false, 'error' => 'Tidak ada model gratis OpenRouter yang tersedia saat ini.', 'tried' => []];
        }

        $candidates = [];
        $preferred = $preferred !== null ? trim($preferred) : '';
        if ($preferred === '' && defined('OPENROUTER_MODEL')) {
            $envPreferred = trim((string) OPENROUTER_MODEL);
            if ($envPreferred !== '' && str_ends_with($envPreferred, ':free')) {
                $preferred = $envPreferred;
            }
        }
        if ($preferred !== '' && str_ends_with($preferred, ':free')) {
            $candidates[] = $preferred;
        }
        foreach ($free as $m) {
            $id = (string) $m['id'];
            if (!in_array($id, $candidates, true)) {
                $candidates[] = $id;
            }
        }
        $candidates = array_slice($candidates, 0, max(1, min(12, $maxModels)));

        $tried = [];
        $authError = '';
        foreach ($candidates as $model) {
            $r = openrouter_chat($model, $system, $user, $json);
            $tried[] = $model . ' => ' . ($r['ok'] ? 'OK' : $r['error']);

            if ($r['ok']) {
                return ['ok' => true, 'text' => $r['text'], 'model' => $model, 'tried' => $tried];
            }

            if (openrouter_is_auth_error((string) ($r['error'] ?? ''))) {
                // Kunci salah/kedaluwarsa: mencoba model lain tidak akan menolong.
                $authError = (string) ($r['error'] ?? '');
                break;
            }

            if (openrouter_is_skip_error((string) ($r['error'] ?? ''))) {
                continue;
            }

            // Sebagian model gratis menolak response_format; ulangi tanpa itu.
            if ($json && (stripos($r['error'], 'response_format') !== false || stripos($r['error'], 'json_object') !== false)) {
                $r2 = openrouter_chat($model, $system, $user, false);
                $tried[] = $model . ' (tanpa json_object) => ' . ($r2['ok'] ? 'OK' : $r2['error']);
                if ($r2['ok']) {
                    return ['ok' => true, 'text' => $r2['text'], 'model' => $model, 'tried' => $tried];
                }
            }

            usleep(350000);
        }

        if ($authError !== '') {
            return [
                'ok'    => false,
                'error' => 'Kunci OpenRouter ditolak. Periksa OPENROUTER_API_KEY di .env. (' . $authError . ')',
                'tried' => $tried,
            ];
        }

        return ['ok' => false, 'error' => 'Semua model gratis yang dicoba gagal.', 'tried' => $tried];
    }
}

if (!function_exists('openrouter_generate_text')) {
    /** @return array{ok:bool,text?:string,model?:string,error?:string,tried?:array} */
    function openrouter_generate_text(string $system, string $user, ?string $preferred = null, int $maxModels = 8): array
    {
        $r = openrouter_try_models($system, $user, $preferred, false, $maxModels);
        if (!$r['ok']) {
            return $r;
        }
        return ['ok' => true, 'text' => $r['text'], 'model' => $r['model'], 'tried' => $r['tried']];
    }
}

if (!function_exists('openrouter_generate_json')) {
    /**
     * Minta JSON. Bila model mengembalikan teks non-JSON, coba sekali lagi
     * dengan instruksi yang lebih tegas sebelum menyerah.
     * @return array{ok:bool,data?:array,model?:string,error?:string,tried?:array}
     */
    function openrouter_generate_json(string $system, string $user, ?string $preferred = null, int $maxModels = 8): array
    {
        $r = openrouter_try_models($system, $user, $preferred, true, $maxModels);
        if (!$r['ok']) {
            return $r;
        }

        $data = openrouter_extract_json((string) $r['text']);
        if (is_array($data)) {
            return ['ok' => true, 'data' => $data, 'model' => $r['model'], 'tried' => $r['tried']];
        }

        $retryUser = $user . "\n\nPENTING: balas HANYA dengan JSON valid, tanpa penjelasan atau code fence.";
        $r2 = openrouter_try_models($system, $retryUser, $r['model'] ?? $preferred, true, 3);
        if ($r2['ok']) {
            $data2 = openrouter_extract_json((string) $r2['text']);
            if (is_array($data2)) {
                return ['ok' => true, 'data' => $data2, 'model' => $r2['model'], 'tried' => array_merge($r['tried'], $r2['tried'])];
            }
        }

        return [
            'ok'    => false,
            'error' => 'Model tidak mengembalikan JSON yang valid.',
            'tried' => array_merge($r['tried'], $r2['tried'] ?? []),
        ];
    }
}
