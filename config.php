<?php
// Pengaturan Database
define('DB_SERVER', 'HOSTNAME-SERVER-HERE');
define('DB_USERNAME', 'USERNAME-DATABASE-HERE'); // Ganti dengan username database Anda
define('DB_PASSWORD', 'DATABASE-PASSWORD-HERE'); // Ganti dengan password database Anda
define('DB_NAME', 'DATABASE-NAME-HERE'); // Ganti dengan nama database Anda

// BARIS BARU: Tambahkan API Key Gemini Anda di sini
define('GEMINI_API_KEY', 'GEMINI-API-KEY-HERE'); // <-- GANTI DENGAN API KEY ANDA

// Membuat koneksi ke database
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Koneksi ke database gagal: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>
