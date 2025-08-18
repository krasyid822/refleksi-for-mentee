<?php
include 'config.php';

// Fungsi panggilGemini harus ada di sini juga.
// NOTE: Praktik terbaik adalah memindahkannya ke file terpisah (e.g. 'helpers.php') dan include di kedua file.
// Tapi untuk simpelnya, kita duplikasi di sini.
function panggilGemini($data_refleksi) {
    if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY' || empty(GEMINI_API_KEY)) {
        return ['sukses' => false, 'error' => 'Kunci API Gemini belum diatur di config.php.'];
    }
    $apiKey = GEMINI_API_KEY;
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $apiKey;
    $prompt = "Berdasarkan data lengkap refleksi mingguan seorang mentee ini, buatkan satu kalimat epik dalam Bahasa Indonesia yang merangkum semangat, pembelajaran, atau kondisi utamanya. Kalimat harus singkat, kuat, dan inspiratif.\n\n" .
              "================ DATA REFLEKSI MENTEE ================\n" .
              "Pencapaian Minggu Ini: " . $data_refleksi['pencapaian'] . "\n" .
              "Kemajuan dari Tujuan Lalu: " . $data_refleksi['kemajuan_tujuan'] . "\n" .
              "Tantangan yang Dihadapi: " . $data_refleksi['tantangan'] . "\n" .
              "Pembelajaran Baru: " . $data_refleksi['pembelajaran_baru'] . "\n" .
              "Momen Paling Berkesan: " . $data_refleksi['momen_berkesan'] . "\n" .
              "Tingkat Energi & Motivasi: " . $data_refleksi['skor_energi'] . "/10 (Catatan: " . $data_refleksi['penjelasan_energi'] . ")\n" .
              "Prioritas Minggu Depan: " . $data_refleksi['prioritas_mingguan'] . "\n" .
              "Catatan Tambahan: " . ($data_refleksi['catatan_lain'] ?: 'Tidak ada') . "\n" .
              "======================================================";
    $data = ['contents' => [['parts' => [['text' => $prompt]]]]];
    $jsonData = json_encode($data);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    if ($curlError) { return ['sukses' => false, 'error' => 'Gagal terhubung ke server AI (cURL Error): ' . $curlError]; }
    if ($httpCode != 200) { return ['sukses' => false, 'error' => 'API Gemini merespon dengan error (Kode: ' . $httpCode . '). Periksa kembali API Key Anda.']; }
    $result = json_decode($response, true);
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $kalimat_epik = trim(str_replace(['*', '"'], '', $result['candidates'][0]['content']['parts'][0]['text']));
        return ['sukses' => true, 'kalimat' => $kalimat_epik];
    } else { return ['sukses' => false, 'error' => 'Gagal mem-parsing respons dari AI. Coba lagi.']; }
}


if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}
$id = intval($_GET['id']);

// 1. Ambil data refleksi dari DB
$sql_select = "SELECT * FROM refleksi_mingguan WHERE id = ?";
$stmt_select = $conn->prepare($sql_select);
$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$result = $stmt_select->get_result();

if ($result->num_rows > 0) {
    $data_refleksi_db = $result->fetch_assoc();

    // 2. Panggil AI dengan data dari DB
    $hasil_ai = panggilGemini($data_refleksi_db);

    if ($hasil_ai['sukses']) {
        // 3. Jika berhasil, update DB
        $sqlUpdate = "UPDATE refleksi_mingguan SET kalimat_epik = ? WHERE id = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->bind_param("si", $hasil_ai['kalimat'], $id);
        $stmtUpdate->execute();
        $stmtUpdate->close();
        
        // 4. Kembali ke halaman detail dengan notif sukses
        header("Location: detail.php?id=" . $id . "&status=regen_sukses");
        exit();
    } else {
        // Jika gagal, kembali dengan notif error
        header("Location: detail.php?id=" . $id . "&status=regen_gagal&error=" . urlencode($hasil_ai['error']));
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>