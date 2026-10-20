<?php
// File: kata-kata-refresh.php
// Endpoint kecil untuk menyegarkan cache kata-kata.json di belakang layar.
// Halaman cukup memanggil endpoint ini saat cache sudah kedaluwarsa.

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/kata_kata_helper.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$isCli = PHP_SAPI === 'cli';
if (!$isCli && !app_is_same_origin_request()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Origin tidak diizinkan.']);
    exit;
}

if (!$isCli && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Gunakan POST.']);
    exit;
}

$force = isset($_REQUEST['force']) && (string) $_REQUEST['force'] === '1';
if ($force && !$isCli) {
    $token = defined('KATA_KATA_REFRESH_TOKEN') ? (string) KATA_KATA_REFRESH_TOKEN : '';
    $given = (string) ($_REQUEST['token'] ?? '');
    if ($token === '' || !hash_equals($token, $given)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Force refresh butuh token.']);
        exit;
    }
}

// Batasi pemicu dari web agar API key tidak dibakar request berulang.
if (!$isCli) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateFile = app_cache_dir() . DIRECTORY_SEPARATOR . 'kata-kata-rate.json';
    $rate = app_json_read($rateFile, ['hits' => []]);
    $now = time();
    $hits = [];
    foreach (($rate['hits'][$ip] ?? []) as $ts) {
        if (is_numeric($ts) && ($now - (int) $ts) < 3600) {
            $hits[] = (int) $ts;
        }
    }
    if (count($hits) >= 4) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => 'Terlalu sering. Coba lagi nanti.']);
        exit;
    }
    $hits[] = $now;
    $rate['hits'][$ip] = $hits;
    app_json_write($rateFile, $rate);
}

@set_time_limit(120);
$result = kata_kata_refresh($force);
$data = kata_kata_load();

echo json_encode([
    'ok'             => (bool) $result['ok'],
    'added'          => (int) ($result['added'] ?? 0),
    'total'          => (int) ($result['total'] ?? count($data['items'])),
    'skipped'        => (bool) ($result['skipped'] ?? false),
    'error'          => $result['error'] ?? null,
    'needs_refresh'  => kata_kata_needs_refresh($data),
    'generated_at'   => $data['generated_at'],
], JSON_UNESCAPED_UNICODE);
