<?php
// File: time.php
// Endpoint JSON untuk menyinkronkan jam analog dengan waktu server.
require_once 'env.php';
require_once 'clock_helper.php';

date_default_timezone_set('Asia/Jakarta');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

$data = baca_waktu_server($conn);

echo json_encode([
    'ok'            => true,
    'php'           => $data['php'],
    'mysql'         => $data['mysql'],
    'mysql_selisih' => $data['mysql_selisih'],
]);
