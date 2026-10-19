<?php
// File: gemini_helper.php

function panggilGeminiDariPrompt($prompt) {
    if (!defined('GEMINI_API_KEY') || GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY' || empty(GEMINI_API_KEY)) {
        return ['sukses' => false, 'error' => 'Kunci API Gemini belum diatur di file .env.'];
    }

    $apiKey = GEMINI_API_KEY;
    $modelCandidates = [
        'gemini-1.5-flash-latest',
        'gemini-1.5-flash',
        'gemini-1.5-pro-latest'
    ];
    $data = ['contents' => [['parts' => [['text' => $prompt]]]]];
    $jsonData = json_encode($data);

    $lastError = 'Respons tidak valid.';
    foreach ($modelCandidates as $model) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $apiKey;

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

        $result = json_decode($response, true);
        if ($httpCode == 200 && isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            $kalimat_epik = trim(str_replace(['*', '"'], '', $result['candidates'][0]['content']['parts'][0]['text']));
            return ['sukses' => true, 'kalimat' => $kalimat_epik];
        }

        $error_message = isset($result['error']['message']) ? $result['error']['message'] : $response;
        $lastError = 'Model ' . $model . ' gagal (Kode: ' . $httpCode . '). Detail: ' . $error_message;

        if ($httpCode != 404) {
            return ['sukses' => false, 'error' => 'API Gemini merespon dengan error. ' . $lastError];
        }
    }

    return ['sukses' => false, 'error' => 'API Gemini merespon dengan error. ' . $lastError];
}

function panggilGemini($data_refleksi) {
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

    return panggilGeminiDariPrompt($prompt);
}

function panggilGeminiPsikologi($data_refleksi) {
    $prompt = "Berdasarkan check-in psikologi singkat seorang mentee ini, buatkan satu kalimat epik dalam Bahasa Indonesia yang terasa hangat, reflektif, dan tidak menghakimi. Hindari diagnosis, istilah klinis, atau nasihat medis. Fokus pada gambaran kondisi batin, ketahanan, dan langkah kecil yang realistis. Kalimat harus singkat, kuat, dan inspiratif.\n\n" .
              "================ CHECK-IN PSIKOLOGI SINGKAT ================\n" .
              "Suasana Hati: " . $data_refleksi['suasana_hati'] . "\n" .
              "Cara Menjawab Tekanan: " . $data_refleksi['reaksi_tekanan'] . "\n" .
              "Kebutuhan Utama Saat Ini: " . $data_refleksi['kebutuhan_utama'] . "\n" .
              "Skor Mood: " . $data_refleksi['mood_level'] . "/10\n" .
              "Skor Fokus: " . $data_refleksi['fokus_level'] . "/10\n" .
              "Skor Pulih: " . $data_refleksi['pulih_level'] . "/10\n" .
              "Pemicu Utama: " . $data_refleksi['pemicu_utama'] . "\n" .
              "Cerita Singkat: " . $data_refleksi['cerita_singkat'] . "\n" .
              "Langkah Kecil Berikutnya: " . $data_refleksi['langkah_kecil'] . "\n" .
              "Topik Diskusi: " . $data_refleksi['topik_diskusi'] . "\n" .
              "Catatan Tambahan: " . ($data_refleksi['catatan_lain'] ?: 'Tidak ada') . "\n" .
              "============================================================";

    return panggilGeminiDariPrompt($prompt);
}
?>