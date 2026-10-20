#!/usr/bin/env bash
#
# build.sh - Naikkan nomor build dan reset cache PWA.
# Dipanggil otomatis oleh push.sh sebelum commit.
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD_FILE="${APP_DIR}/build.json"
SW_FILE="${APP_DIR}/service-worker.js"

if ! command -v php >/dev/null 2>&1; then
  echo "PHP CLI tidak ditemukan." >&2
  exit 1
fi

export BUILD_FILE SW_FILE

php <<'PHP'
<?php
$buildFile = (string) getenv('BUILD_FILE');
$swFile    = (string) getenv('SW_FILE');

$build = [];
if (is_file($buildFile)) {
    $raw = @file_get_contents($buildFile);
    $decoded = $raw !== false ? json_decode($raw, true) : null;
    if (is_array($decoded)) {
        $build = $decoded;
    }
}

$version = (int) ($build['version'] ?? 0) + 1;
$buildId = date('YmdHis') . '-' . $version;
$build = [
    'version'  => $version,
    'build_id' => $buildId,
    'built_at' => date('c'),
];

$json = json_encode($build, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json === false || file_put_contents($buildFile, $json . "\n", LOCK_EX) === false) {
    fwrite(STDERR, "Gagal menulis build.json.\n");
    exit(1);
}

$sw = @file_get_contents($swFile);
if ($sw === false) {
    fwrite(STDERR, "service-worker.js tidak ditemukan.\n");
    exit(1);
}

$newSw = preg_replace("/const BUILD_ID = '[^']*';/", "const BUILD_ID = '" . $buildId . "';", $sw, 1, $count);
if ($newSw === null || $count !== 1) {
    fwrite(STDERR, "Penanda BUILD_ID di service-worker.js tidak ditemukan.\n");
    exit(1);
}

$tmp = $swFile . '.tmp.' . getmypid();
if (file_put_contents($tmp, $newSw, LOCK_EX) === false || !rename($tmp, $swFile)) {
    @unlink($tmp);
    fwrite(STDERR, "Gagal menulis service-worker.js.\n");
    exit(1);
}

echo "Build #{$version} ({$buildId}) siap.\n";
PHP
