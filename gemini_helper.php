<?php
// File: gemini_helper.php

function panggilGemini($data_refleksi) {
    if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY' || empty(GEMINI_API_KEY)) {
        return ['sukses' => false, 'error' => 'Kunci API Gemini belum diatur di config.php.'];
    }

    $apiKey = GEMINI_API_KEY;
    
    // DIGANTI: Menggunakan model gemini-1.5-flash-latest yang lebih cepat dan efisien
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $apiKey;
    
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
    
    if ($curlError) {
        return ['sukses' => false, 'error' => 'Gagal terhubung ke server AI (cURL Error): ' . $curlError];
    }
    if ($httpCode != 200) {
        $error_details = json_decode($response, true);
        $error_message = isset($error_details['error']['message']) ? $error_details['error']['message'] : $response;
        return ['sukses' => false, 'error' => 'API Gemini merespon dengan error (Kode: ' . $httpCode . '). Detail: ' . $error_message];
    }

    $result = json_decode($response, true);
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        $kalimat_epik = trim(str_replace(['*', '"'], '', $result['candidates'][0]['content']['parts'][0]['text']));
        return ['sukses' => true, 'kalimat' => $kalimat_epik];
    } else {
        $error_details = isset($result['error']['message']) ? $result['error']['message'] : 'Respons tidak valid.';
        return ['sukses' => false, 'error' => 'Gagal mem-parsing respons dari AI. Detail: ' . $error_details];
    }
}
?>