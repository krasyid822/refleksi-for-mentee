<?php
// File: ai_proxy.php
// Proxy server-side untuk fitur AI di form psikologi.
// Kunci OpenRouter tetap di server; browser hanya menerima model & hasil teks.
//
// Aksi:
//   GET/POST ?action=models  -> daftar model gratis
//   POST     ?action=epic    -> {model, prompt} -> kalimat epik
//   GET      ?action=status  -> status kunci (tanpa membocorkan nilainya)

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/openrouter_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function ai_proxy_out(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$isCli = PHP_SAPI === 'cli';
if (!$isCli && !app_is_same_origin_request()) {
    ai_proxy_out(['ok' => false, 'error' => 'Origin tidak diizinkan.'], 403);
}

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? 'status');

if ($action === 'status') {
    ai_proxy_out([
        'ok'          => true,
        'configured'  => openrouter_api_key() !== '',
        'model_count' => count(openrouter_free_models()),
    ]);
}

if ($action === 'models') {
    $models = openrouter_free_models();
    $out = [];
    foreach ($models as $m) {
        $out[] = ['id' => (string) $m['id'], 'context' => (int) ($m['context'] ?? 0)];
    }
    if ($out === []) {
        ai_proxy_out(['ok' => false, 'models' => [], 'error' => 'Tidak ada model gratis yang tersedia.'], 503);
    }
    ai_proxy_out(['ok' => true, 'models' => $out]);
}

if ($action !== 'epic') {
    ai_proxy_out(['ok' => false, 'error' => 'Aksi tidak dikenal.'], 400);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ai_proxy_out(['ok' => false, 'error' => 'Gunakan POST.'], 405);
}

// Rate limit per IP: maksimal 20 generate per jam.
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateFile = app_cache_dir() . DIRECTORY_SEPARATOR . 'ai_proxy_rate.json';
$rate = app_json_read($rateFile, ['hits' => []]);
$now = time();
$hits = [];
foreach (($rate['hits'][$ip] ?? []) as $ts) {
    if (is_numeric($ts) && ($now - (int) $ts) < 3600) {
        $hits[] = (int) $ts;
    }
}
if (count($hits) >= 20) {
    ai_proxy_out(['ok' => false, 'error' => 'Terlalu banyak permintaan AI. Coba lagi nanti.'], 429);
}
$hits[] = $now;
$rate['hits'][$ip] = $hits;
app_json_write($rateFile, $rate);

$raw = file_get_contents('php://input');
$body = [];
if (is_string($raw) && trim($raw) !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $body = $decoded;
    }
}
if ($body === []) {
    $body = $_POST;
}

$model = trim((string) ($body['model'] ?? ''));
$prompt = trim((string) ($body['prompt'] ?? ''));

if ($model === '' || !str_ends_with($model, ':free')) {
    ai_proxy_out(['ok' => false, 'error' => 'Model harus model gratis berakhiran :free.'], 422);
}
if ($prompt === '' || strlen($prompt) > 12000) {
    ai_proxy_out(['ok' => false, 'error' => 'Prompt kosong atau terlalu panjang.'], 422);
}
if (openrouter_api_key() === '') {
    ai_proxy_out(['ok' => false, 'error' => 'Kunci OpenRouter belum diatur di .env.'], 503);
}

@set_time_limit(120);

$system = 'Kamu menulis satu kalimat epik dalam Bahasa Indonesia yang singkat, hangat, reflektif,'
    . ' tidak menghakimi, tanpa diagnosis medis, dan tanpa nasihat yang menggurui.'
    . ' Balas hanya kalimatnya, maksimal 35 kata, tanpa tanda kutip pembungkus.';

$result = openrouter_generate_text($system, $prompt, $model, 6);
if (!$result['ok']) {
    ai_proxy_out([
        'ok'    => false,
        'error' => $result['error'] ?? 'Gagal menghubungi OpenRouter.',
        'tried' => array_slice($result['tried'] ?? [], 0, 8),
    ], 502);
}

$text = trim((string) $result['text']);
$text = trim($text, " \t\n\r\0\x0B\"'");
if ($text === '') {
    ai_proxy_out(['ok' => false, 'error' => 'Model mengembalikan teks kosong.'], 502);
}

ai_proxy_out([
    'ok'    => true,
    'text'  => $text,
    'model' => $result['model'] ?? $model,
]);
