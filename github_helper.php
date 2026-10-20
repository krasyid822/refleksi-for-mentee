<?php
// File: github_helper.php
// Membuat issue GitHub untuk kasus struktur data yang belum didukung AI.
// Token dan repo diambil dari .env (server-side, tidak pernah ke browser).

if (!defined('APP_BOOTSTRAPPED')) {
    require_once __DIR__ . '/env.php';
}
require_once __DIR__ . '/app_helper.php';

if (!function_exists('github_config')) {
    function github_config(): array
    {
        $token = defined('GITHUB_TOKEN') ? trim((string) GITHUB_TOKEN) : '';
        $repo = defined('GITHUB_REPO') ? trim((string) GITHUB_REPO) : '';
        if ($repo === '') {
            $repo = 'krasyid822/refleksi-for-mentee';
        }
        return ['token' => $token, 'repo' => $repo];
    }
}

if (!function_exists('github_queue_issue')) {
    function github_queue_issue(string $title, string $body): bool
    {
        $file = app_cache_dir() . DIRECTORY_SEPARATOR . 'github_issue_queue.json';
        $queue = app_json_read($file, ['items' => []]);
        if (!isset($queue['items']) || !is_array($queue['items'])) {
            $queue['items'] = [];
        }
        $queue['items'][] = [
            'title'     => $title,
            'body'      => $body,
            'queued_at' => date('c'),
        ];
        return app_json_write($file, $queue);
    }
}

if (!function_exists('github_create_issue')) {
    /**
     * @return array{ok:bool,number?:int,url?:string,error?:string,queued?:bool}
     */
    function github_create_issue(string $title, string $body, array $labels = []): array
    {
        $cfg = github_config();
        if ($cfg['token'] === '') {
            github_queue_issue($title, $body);
            return [
                'ok'     => false,
                'queued' => true,
                'error'  => 'Token GitHub belum diatur; laporan disimpan sementara di cache/issue_queue.',
            ];
        }

        $payload = ['title' => $title, 'body' => $body];
        if ($labels !== []) {
            $payload['labels'] = array_values(array_filter(array_map('strval', $labels)));
        }

        $headers = [
            'Accept: application/vnd.github+json',
            'Authorization: Bearer ' . $cfg['token'],
            'Content-Type: application/json',
            'User-Agent: refleksi-for-mentee',
            'X-GitHub-Api-Version: 2022-11-28',
        ];

        $ch = curl_init('https://api.github.com/repos/' . $cfg['repo'] . '/issues');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $code < 200 || $code >= 300) {
            $short = $resp === false ? $err : preg_replace('/\s+/', ' ', (string) $resp);
            if (function_exists('mb_substr')) {
                $short = mb_substr($short, 0, 300);
            } else {
                $short = substr($short, 0, 300);
            }
            github_queue_issue($title, $body);
            return [
                'ok'     => false,
                'queued' => true,
                'error'  => 'Gagal membuat issue GitHub (HTTP ' . $code . '). ' . $short,
            ];
        }

        $d = json_decode((string) $resp, true);
        return [
            'ok'     => true,
            'number' => (int) ($d['number'] ?? 0),
            'url'    => (string) ($d['html_url'] ?? ''),
        ];
    }
}

if (!function_exists('github_sanitize_payload')) {
    function github_sanitize_payload(mixed $value, string $key = ''): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = github_sanitize_payload($v, is_string($k) ? $k : '');
            }
            return $out;
        }
        if (is_string($value)) {
            $sensitive = ['nama_mentee', 'nama_mentor', 'sandi_nama', 'nama', 'email', 'telepon', 'phone'];
            if (in_array(strtolower($key), $sensitive, true)) {
                return '[disamarkan]';
            }
            return app_redact_text($value, 400);
        }
        return $value;
    }
}

if (!function_exists('github_build_issue_body')) {
    /**
     * Susun body issue yang aman: teks mentah disamarkan, struktur hasil
     * ekstraksi tetap disertakan agar bisa dipakai untuk menambah varian tabel.
     */
    function github_build_issue_body(string $rawText, ?array $expected, ?array $actual, string $reason = ''): string
    {
        $expected = $expected !== null ? github_sanitize_payload($expected) : null;
        $actual = $actual !== null ? github_sanitize_payload($actual) : null;
        $lines = [];
        $lines[] = '## Ringkasan';
        $lines[] = 'Form bebas menerima teks yang tidak bisa dipetakan ke varian tabel yang ada.';
        $lines[] = '';
        $lines[] = '> Catatan privasi: email, nomor telepon, dan tautan disamarkan otomatis. Nama atau detail unik masih mungkin muncul; gunakan repo privat bila issue ini tidak boleh publik.';
        if ($reason !== '') {
            $lines[] = '';
            $lines[] = '**Alasan:** ' . $reason;
        }

        $lines[] = '';
        $lines[] = '## Cuplikan teks (disamarkan)';
        $lines[] = '```text';
        $lines[] = app_redact_text($rawText, 1200);
        $lines[] = '```';

        if ($expected !== null) {
            $lines[] = '';
            $lines[] = '## Struktur yang diharapkan AI';
            $lines[] = '```json';
            $lines[] = json_encode($expected, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $lines[] = '```';
        }

        if ($actual !== null) {
            $lines[] = '';
            $lines[] = '## Hasil ekstraksi aktual';
            $lines[] = '```json';
            $lines[] = json_encode($actual, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $lines[] = '```';
        }

        $lines[] = '';
        $lines[] = '## Tindak lanjut';
        $lines[] = '- [ ] Tentukan varian tabel yang cocok, atau tambahkan tabel baru.';
        $lines[] = '- [ ] Sesuaikan prompt pemetaan skema bila perlu.';
        $lines[] = '- [ ] Uji ulang form bebas dengan contoh serupa.';

        return implode("\n", $lines);
    }
}
