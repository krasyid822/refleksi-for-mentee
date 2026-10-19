<?php
require_once 'env.php';
include 'gemini_helper.php';

$pesan_sukses = '';
$pesan_error = '';
$redirect_after_success = false;

// Inisialisasi variabel untuk menyimpan nilai input
$input_values = [
    'nama_mentee' => '', 'nama_mentor' => '', 'samarkan_nama' => false, 'sandi_nama' => '',
    'periode_minggu' => '', 'periode_tanggal' => '',
    'mood_level' => 5, 'energy_level' => 5, 'stress_level' => 5,
    'achievement_tags' => [], 'challenge_tags' => [], 'learning_tags' => [],
    'next_week_focus' => [], 'mentor_help' => [],
    'pencapaian_text' => '', 'tantangan_text' => '', 'pembelajaran_text' => '',
    'momen_berkesan' => '', 'tujuan_spesifik' => '', 'topik_diskusi' => '', 'catatan_lain' => ''
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Simpan semua inputan
    foreach ($input_values as $key => $value) {
        if (isset($_POST[$key])) {
            $input_values[$key] = $_POST[$key];
        }
    }
    $input_values['samarkan_nama'] = isset($_POST['samarkan_nama']);

    // Compile data untuk AI
    $mood_emoji = ['😢', '😕', '😐', '🙂', '😊', '😁', '🤩', '🥳', '🤗', '🎉'];
    $energy_emoji = ['😴', '🥱', '😑', '😌', '🙂', '😊', '😃', '💪', '🔥', '⚡'];
    $stress_emoji = ['😌', '😇', '🙂', '😐', '😅', '😰', '😓', '😫', '😩', '🤯'];
    
    $mood_desc = $mood_emoji[$_POST['mood_level'] - 1] ?? '😊';
    $energy_desc = $energy_emoji[$_POST['energy_level'] - 1] ?? '😊';
    $stress_desc = $stress_emoji[$_POST['stress_level'] - 1] ?? '😌';

    // Compile tags jadi kalimat
    $achievement_str = isset($_POST['achievement_tags']) ? implode(", ", $_POST['achievement_tags']) : '';
    $challenge_str = isset($_POST['challenge_tags']) ? implode(", ", $_POST['challenge_tags']) : '';
    $learning_str = isset($_POST['learning_tags']) ? implode(", ", $_POST['learning_tags']) : '';
    
    // Buat narasi untuk AI
    $pencapaian_full = "Pencapaian yang dipilih: " . $achievement_str . "\nDetail: " . $_POST['pencapaian_text'];
    $tantangan_full = "Tantangan yang dihadapi: " . $challenge_str . "\nDetail: " . $_POST['tantangan_text'];
    $pembelajaran_full = "Pembelajaran: " . $learning_str . "\nDetail: " . $_POST['pembelajaran_text'];
    
    $kemajuan_tujuan = "Mood: " . $mood_desc . " (Level " . $_POST['mood_level'] . "/10)\n";
    $kemajuan_tujuan .= "Energi: " . $energy_desc . " (Level " . $_POST['energy_level'] . "/10)\n";
    $kemajuan_tujuan .= "Stress: " . $stress_desc . " (Level " . $_POST['stress_level'] . "/10)";
    
    $prioritas_mingguan = isset($_POST['next_week_focus']) ? implode(", ", $_POST['next_week_focus']) : '';

    $data_refleksi_ai = [
        'pencapaian' => $pencapaian_full,
        'kemajuan_tujuan' => $kemajuan_tujuan,
        'tantangan' => $tantangan_full,
        'pembelajaran_baru' => $pembelajaran_full,
        'skor_energi' => $_POST['energy_level'],
        'penjelasan_energi' => "Mood level: " . $_POST['mood_level'] . ", Stress level: " . $_POST['stress_level'],
        'momen_berkesan' => $_POST['momen_berkesan'],
        'prioritas_mingguan' => $prioritas_mingguan . " - " . $_POST['tujuan_spesifik'],
        'catatan_lain' => $_POST['catatan_lain']
    ];
    
    // Panggil AI
    $hasil_ai = panggilGemini($data_refleksi_ai);

    if (!$hasil_ai['sukses']) {
        $pesan_error = "PROSES SUBMIT DIBATALKAN. Kegagalan AI: " . htmlspecialchars($hasil_ai['error']) . " Data Anda di bawah ini aman dan tidak hilang, silakan coba submit lagi. Atau coba hubungi pembuat aplikasi ini.";
    } else {
        $kalimat_epik = $hasil_ai['kalimat'];
        
        $nama_mentee = $_POST['nama_mentee'];
        $nama_mentor = $_POST['nama_mentor'];
        $nama_disamarkan = isset($_POST['samarkan_nama']) ? 1 : 0;
        $sandi_nama = ($nama_disamarkan == 1 && !empty($_POST['sandi_nama'])) ? $_POST['sandi_nama'] : null;
        
        $periode_minggu_raw = trim($_POST['periode_minggu'] ?? '');
        $periode_minggu = $periode_minggu_raw === '' ? null : (int)$periode_minggu_raw;
        $periode_tanggal = $_POST['periode_tanggal'];
        $topik_diskusi = $_POST['topik_diskusi'];
        $bantuan_diharapkan = isset($_POST['mentor_help']) ? implode(", ", $_POST['mentor_help']) : '';
        
        // Variables untuk bind_param (semua harus variable, tidak bisa ekspresi atau array element langsung)
        $skor_energi = $_POST['energy_level'];
        $penjelasan_energi = "Mood: " . $_POST['mood_level'] . ", Stress: " . $_POST['stress_level'];
        $momen_berkesan = $_POST['momen_berkesan'];
        $tujuan_spesifik = $_POST['tujuan_spesifik'];
        $catatan_lain = $_POST['catatan_lain'];
        
        $sql = "INSERT INTO refleksi_mingguan (nama_mentee, nama_mentor, nama_disamarkan, sandi_nama, periode_minggu, periode_tanggal, pencapaian, kemajuan_tujuan, tantangan, pembelajaran_baru, skor_energi, penjelasan_energi, momen_berkesan, prioritas_mingguan, tujuan_spesifik, topik_diskusi, bantuan_diharapkan, catatan_lain, kalimat_epik) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssisisssssissssssss", 
            $nama_mentee, $nama_mentor, $nama_disamarkan, $sandi_nama, $periode_minggu, $periode_tanggal,
            $pencapaian_full, $kemajuan_tujuan, $tantangan_full, $pembelajaran_full,
            $skor_energi, $penjelasan_energi,
            $momen_berkesan, $prioritas_mingguan, $tujuan_spesifik,
            $topik_diskusi, $bantuan_diharapkan, $catatan_lain, $kalimat_epik);

        if ($stmt->execute()) {
            $pesan_sukses = "🎉 Refleksi berhasil disubmit! Kalimat epikmu sudah dibuat oleh AI!";
            $redirect_after_success = true;
        } else {
            $pesan_error = "Data gagal disimpan. Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

$should_restore_progress = ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($pesan_error));

$riwayat_sql = "SELECT id, nama_mentee, nama_disamarkan, periode_minggu, periode_tanggal, tanggal_submit FROM refleksi_mingguan ORDER BY tanggal_submit DESC";
$result = $conn->query($riwayat_sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refleksi Interaktif - UKMI</title>
    <meta name="theme-color" content="#083344">
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="icons/icon-192.svg">
    <link rel="apple-touch-icon" href="icons/icon-192.svg">
    <script src="audio-player.js" defer></script>
    <script src="pwa-register.js" defer></script>
    <script>
        window.SERVER_PROGRESS_SHOULD_RESTORE = <?php echo $should_restore_progress ? 'true' : 'false'; ?>;
        window.SERVER_PROGRESS_DATA = <?php echo $should_restore_progress
            ? json_encode($input_values, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)
            : 'null'; ?>;
        window.SUBMIT_SUCCESS = <?php echo $redirect_after_success ? 'true' : 'false'; ?>;
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600&family=Space+Grotesk:wght@400;500&display=swap');

        :root {
            --ink: #06283d;
            --bg-main: #083344;
            --card: #effcff;
            --accent: #06b6d4;
            --accent-dark: #0f4c5c;
            --muted: rgba(6, 40, 61, 0.72);
            --outline: #0f4c5c;
            --soft-shadow: #04212c;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Space Grotesk', 'Poppins', -apple-system, BlinkMacSystemFont, system-ui, sans-serif;
            background: radial-gradient(circle at top left, rgba(14, 165, 233, 0.24), transparent 32%),
                        radial-gradient(circle at top right, rgba(16, 185, 129, 0.22), transparent 30%),
                        var(--bg-main);
            color: #f8fafc;
            min-height: 100vh;
            padding: 32px 18px 72px;
        }

        .container {
            max-width: 960px;
            margin: 0 auto 40px;
            background: var(--card);
            color: var(--ink);
            border-radius: 32px;
            border: 3px solid var(--outline);
            box-shadow: 18px 24px 0 var(--soft-shadow);
            padding: 40px;
        }

        .nav-links {
            display: flex;
            gap: 16px;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }

        .nav-link {
            flex: 1;
            min-width: 180px;
            text-align: center;
            padding: 14px 18px;
            border-radius: 999px;
            border: 3px solid var(--outline);
            background: #dbeafe;
            color: var(--ink);
            font-weight: 600;
            text-decoration: none;
            box-shadow: 6px 8px 0 var(--soft-shadow);
            transition: transform 0.15s ease;
        }

        .nav-link:hover { transform: translateY(-2px); }

        .nav-link.active { background: #a7f3d0; color: var(--accent-dark); }

        h1 {
            font-family: 'Fredoka', sans-serif;
            text-transform: uppercase;
            /* text-align: center; */
            color: #0f4c5c;
            font-size: clamp(2rem, 5vw, 3.2rem);
            letter-spacing: 0.08em;
        }

        .subtitle {
            /* text-align: center; */
            margin: 12px 0 30px;
            color: var(--muted);
            font-size: 1.05rem;
        }

        html { scroll-behavior: smooth; }

        .riwayat h2 {
            font-family: 'Fredoka', sans-serif;
            color: var(--ink);
            font-size: 1.6rem;
            margin-bottom: 6px;
        }

        h3 {
            font-family: 'Fredoka', sans-serif;
            font-size: 1.3rem;
            margin: 22px 0 12px;
            color: var(--ink);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-divider {
            border: none;
            height: 3px;
            background: var(--outline);
            margin: 28px 0 18px;
            border-radius: 999px;
            opacity: 0.15;
        }

        label {
            font-weight: 600;
            display: block;
            margin-top: 14px;
            margin-bottom: 6px;
            color: var(--ink);
        }

        input[type="text"],
        input[type="number"],
        input[type="password"],
        textarea {
            width: 100%;
            border: 2px solid var(--outline);
            border-radius: 20px;
            padding: 14px 18px;
            font-size: 1rem;
            font-family: inherit;
            background: #fff;
            color: var(--ink);
            box-shadow: 6px 8px 0 #c9d4ff;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: var(--accent-dark);
            box-shadow: 6px 8px 0 var(--soft-shadow);
        }

        textarea { min-height: 100px; resize: vertical; }

        .privacy-box {
            border: 2px dashed var(--outline);
            border-radius: 20px;
            padding: 16px;
            background: #f7f8ff;
            margin-top: 12px;
            box-shadow: 6px 8px 0 #c9d4ff;
        }

        .privacy-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .privacy-toggle label {
            margin: 0;
            font-weight: 500;
        }

        #kolom_sandi { display: none; margin-top: 14px; }

        #kolom_sandi label { margin-top: 0; }

        .slider-container {
            background: #fff;
            border: 2px solid var(--outline);
            border-radius: 24px;
            padding: 16px 18px;
            box-shadow: 6px 8px 0 #c9d4ff;
            margin: 12px 0;
        }

        .slider-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .slider-emoji { font-size: 2.2rem; transition: transform 0.2s ease; }

        .slider-value { font-size: 1.4rem; font-weight: 700; color: var(--accent-dark); }

        input[type="range"] {
            width: 100%;
            height: 10px;
            border-radius: 999px;
            background: linear-gradient(90deg, #a5b4fc, #fcd34d);
            outline: none;
            -webkit-appearance: none;
        }

        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: #fff;
            border: 3px solid var(--outline);
            box-shadow: 4px 6px 0 var(--soft-shadow);
            cursor: pointer;
            transition: transform 0.15s ease;
        }

        input[type="range"]::-webkit-slider-thumb:hover { transform: scale(1.1); }

        .tags-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 14px;
            margin-top: 8px;
        }

        .tag-item { position: relative; }

        .tag-item input[type="checkbox"] { position: absolute; opacity: 0; }

        .tag-label {
            display: block;
            padding: 16px 20px;
            border: 2px solid var(--outline);
            border-radius: 24px;
            text-align: center;
            background: #fff;
            font-weight: 600;
            cursor: pointer;
            color: var(--ink);
            box-shadow: 6px 8px 0 #c9d4ff;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .tag-item input:checked + .tag-label {
            background: var(--accent);
            color: var(--accent-dark);
            transform: translateY(-3px);
            box-shadow: 10px 12px 0 var(--soft-shadow);
        }

        .tag-label:hover { transform: translateY(-2px); }

        .helper-text { color: var(--muted); margin: 0 0 12px; font-size: 0.95rem; }
        .helper-text.spaced { margin-top: 20px; }

        button {
            background: var(--accent);
            color: var(--accent-dark);
            border: 3px solid var(--outline);
            border-radius: 999px;
            padding: 16px 28px;
            font-weight: 700;
            font-size: 1.05rem;
            width: 100%;
            margin-top: 24px;
            box-shadow: 12px 14px 0 var(--soft-shadow);
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        button:hover { transform: translateY(-3px); box-shadow: 16px 18px 0 var(--soft-shadow); }

        button:active { transform: translateY(0); box-shadow: 8px 10px 0 var(--soft-shadow); }

        .success-message,
        .error-message {
            border-radius: 24px;
            border: 3px solid var(--outline);
            padding: 16px 20px;
            font-weight: 600;
            margin-bottom: 24px;
            box-shadow: 8px 10px 0 var(--soft-shadow);
            text-align: center;
        }

        .success-message { background: #cffafe; color: #155e75; }
        .error-message { background: #ffe4e6; color: #7f1d1d; }

        .table-responsive-wrapper {
            margin-top: 18px;
            border: 3px solid var(--outline);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 12px 16px 0 var(--soft-shadow);
            background: #fff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            color: var(--ink);
        }

        th,
        td {
            padding: 16px;
            border-bottom: 1px solid #d6deff;
            text-align: left;
        }

        th {
            background: #cdd7ff;
            font-family: 'Fredoka', sans-serif;
            text-transform: uppercase;
            font-size: 0.85rem;
        }

        tr:nth-child(even) { background: #f8f9ff; }

        tr:hover { background: #f0f4ff; }

        a { color: var(--accent-dark); font-weight: 600; }

        .table-empty { text-align: center; padding: 18px 16px; }

        .toast {
            position: fixed;
            top: 24px;
            right: 24px;
            background: #bbf7d0;
            color: var(--accent-dark);
            border: 3px solid var(--outline);
            border-radius: 16px;
            padding: 14px 20px;
            box-shadow: 8px 12px 0 var(--soft-shadow);
            font-weight: 600;
            z-index: 50;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        @media (max-width: 768px) {
            body { padding: 20px 12px 60px; }
            .container { padding: 28px 20px; box-shadow: 12px 18px 0 var(--soft-shadow); }
            .nav-links { gap: 8px; margin-bottom: 22px; }
            .nav-link {
                flex: 1 1 calc(50% - 4px);
                min-width: 0;
                padding: 11px 10px;
                font-size: 0.9rem;
                line-height: 1.2;
                border-radius: 18px;
                box-shadow: 4px 6px 0 var(--soft-shadow);
            }
            .nav-link:last-child { flex-basis: 100%; }
            .tags-grid { grid-template-columns: 1fr; }
            table { min-width: 620px; }
            .table-responsive-wrapper { overflow-x: auto; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="nav-links">
        <a href="index.php" class="nav-link">🏠 Dashboard</a>
        <a href="form.php" class="nav-link">📝 Form Klasik</a>
        <a href="form_interactive.php" class="nav-link active">✨ Form Interaktif</a>
        <a href="form_psikologi.php" class="nav-link">🧠 Tes Psikologi</a>
        <a href="#tabel-riwayat" class="nav-link">🔎 Detail</a>
    </div>

    <h1>Formulir Refleksi Mingguan Ukmi+</h1>
    <p class="subtitle">Ekspresikan perjalananmu dengan cara yang lebih fun! 🎨</p>
    
    <!-- <div class="progress-controls">
        <button type="button" class="progress-btn" onclick="saveProgress(); showNotification('💾 Progress tersimpan!');">💾 Simpan Progress</button>
        <button type="button" class="progress-btn" onclick="restoreProgress();">📥 Muat Progress</button>
        <button type="button" class="progress-btn" onclick="clearProgress();">🗑️ Hapus Progress</button>
    </div> -->

    <?php if (!empty($pesan_sukses)): ?>
        <div class="success-message"><?php echo $pesan_sukses; ?></div>
    <?php endif; ?>
    <?php if (!empty($pesan_error)): ?>
        <div class="error-message"><?php echo $pesan_error; ?></div>
    <?php endif; ?>

    <form action="form_interactive.php" method="post">
        <h3>👤 Info Dasar</h3>
        <label for="nama_mentee">Nama Mentee</label>
        <input type="text" id="nama_mentee" name="nama_mentee" required>
        
        <label for="nama_mentor">Nama Mentor</label>
        <input type="text" id="nama_mentor" name="nama_mentor" required>
        
        <div class="privacy-box">
            <div class="privacy-toggle">
                <input type="checkbox" id="samarkan_nama" name="samarkan_nama" value="1">
                <label for="samarkan_nama">🔒 Samarkan nama</label>
            </div>
            <div id="kolom_sandi">
                <label for="sandi_nama">Sandi:</label>
                <input type="password" id="sandi_nama" name="sandi_nama">
            </div>
        </div>
        
        <label for="periode_minggu">Minggu ke- <span style="font-weight:400; color: var(--muted);">(opsional)</span></label>
        <input type="number" id="periode_minggu" name="periode_minggu" min="1" placeholder="Kosongkan jika tidak ingat">
        
        <label for="periode_tanggal">Tanggal Periode</label>
        <input type="text" id="periode_tanggal" name="periode_tanggal" placeholder="12 Agu - 18 Agu 2025" required>
        
        <hr class="section-divider">
        <h3>😊 Bagaimana Perasaanmu Minggu Ini?</h3>
        
        <div class="slider-container">
            <div class="slider-label">
                <span><strong>Mood Level:</strong></span>
                <span class="slider-emoji" id="mood-emoji">😊</span>
                <span class="slider-value" id="mood-value">5</span>
            </div>
            <input type="range" id="mood-slider" name="mood_level" min="1" max="10" value="5">
        </div>
        
        <div class="slider-container">
            <div class="slider-label">
                <span><strong>Energy Level:</strong></span>
                <span class="slider-emoji" id="energy-emoji">😊</span>
                <span class="slider-value" id="energy-value">5</span>
            </div>
            <input type="range" id="energy-slider" name="energy_level" min="1" max="10" value="5">
        </div>
        
        <div class="slider-container">
            <div class="slider-label">
                <span><strong>Stress Level:</strong></span>
                <span class="slider-emoji" id="stress-emoji">😐</span>
                <span class="slider-value" id="stress-value">5</span>
            </div>
            <input type="range" id="stress-slider" name="stress_level" min="1" max="10" value="5">
        </div>
        
        <hr class="section-divider">
        <h3>🎯 Pencapaian Minggu Ini</h3>
        <p class="helper-text">Pilih yang sesuai (bisa lebih dari 1):</p>
        <div class="tags-grid">
            <div class="tag-item">
                <input type="checkbox" id="ach1" name="achievement_tags[]" value="Tugas selesai tepat waktu">
                <label for="ach1" class="tag-label">✅ Tugas on time</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="ach2" name="achievement_tags[]" value="Ikut kegiatan UKMI">
                <label for="ach2" class="tag-label">🕌 Kegiatan UKMI</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="ach3" name="achievement_tags[]" value="Belajar skill baru">
                <label for="ach3" class="tag-label">📚 Skill baru</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="ach4" name="achievement_tags[]" value="Membantu teman">
                <label for="ach4" class="tag-label">🤝 Bantu teman</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="ach5" name="achievement_tags[]" value="IPK naik">
                <label for="ach5" class="tag-label">📈 IPK naik</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="ach6" name="achievement_tags[]" value="Dapat insight baru">
                <label for="ach6" class="tag-label">💡 Insight baru</label>
            </div>
        </div>
        
        <label for="pencapaian_text">Ceritakan lebih detail (opsional):</label>
        <textarea id="pencapaian_text" name="pencapaian_text" placeholder="Tambahkan cerita atau detail tentang pencapaianmu..."></textarea>
        
        <hr class="section-divider">
        <h3>🚧 Tantangan yang Dihadapi</h3>
        <p class="helper-text">Apa yang bikin kamu struggle?</p>
        <div class="tags-grid">
            <div class="tag-item">
                <input type="checkbox" id="ch1" name="challenge_tags[]" value="Manajemen waktu">
                <label for="ch1" class="tag-label">⏰ Waktu</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="ch2" name="challenge_tags[]" value="Materi kuliah sulit">
                <label for="ch2" class="tag-label">📖 Materi sulit</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="ch3" name="challenge_tags[]" value="Kurang motivasi">
                <label for="ch3" class="tag-label">😔 Motivasi</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="ch4" name="challenge_tags[]" value="Masalah pribadi">
                <label for="ch4" class="tag-label">💭 Personal</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="ch5" name="challenge_tags[]" value="Keuangan">
                <label for="ch5" class="tag-label">💰 Keuangan</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="ch6" name="challenge_tags[]" value="Kesehatan">
                <label for="ch6" class="tag-label">🏥 Kesehatan</label>
            </div>
        </div>
        
        <label for="tantangan_text">Ceritakan lebih detail:</label>
        <textarea id="tantangan_text" name="tantangan_text" placeholder="Jelaskan tantangan yang kamu hadapi..."></textarea>
        
        <hr class="section-divider">
        <h3>🧠 Pembelajaran Minggu Ini</h3>
        <p class="helper-text">Apa yang kamu pelajari?</p>
        <div class="tags-grid">
            <div class="tag-item">
                <input type="checkbox" id="lr1" name="learning_tags[]" value="Skill teknis">
                <label for="lr1" class="tag-label">💻 Skill teknis</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="lr2" name="learning_tags[]" value="Soft skill">
                <label for="lr2" class="tag-label">🗣️ Soft skill</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="lr3" name="learning_tags[]" value="Self awareness">
                <label for="lr3" class="tag-label">🪞 Self aware</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="lr4" name="learning_tags[]" value="Networking">
                <label for="lr4" class="tag-label">🌐 Networking</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="lr5" name="learning_tags[]" value="Spiritualitas">
                <label for="lr5" class="tag-label">🤲 Spiritual</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="lr6" name="learning_tags[]" value="Life lesson">
                <label for="lr6" class="tag-label">💫 Life lesson</label>
            </div>
        </div>
        
        <label for="pembelajaran_text">Jelaskan apa yang kamu pelajari:</label>
        <textarea id="pembelajaran_text" name="pembelajaran_text" placeholder="Share insight atau pembelajaran barumu..."></textarea>
        
        <hr class="section-divider">
        <h3>🌟 Momen Berkesan</h3>
        <label for="momen_berkesan">Ceritakan 1 momen yang paling memorable:</label>
        <textarea id="momen_berkesan" name="momen_berkesan" required placeholder="Momen yang bikin kamu bahagia, bangga, atau terkesan..."></textarea>
        
        <hr class="section-divider">
        <h3>🎯 Rencana Minggu Depan</h3>
        <p class="helper-text">Apa fokus utama kamu?</p>
        <div class="tags-grid">
            <div class="tag-item">
                <input type="checkbox" id="fc1" name="next_week_focus[]" value="Fokus akademik">
                <label for="fc1" class="tag-label">📚 Akademik</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="fc2" name="next_week_focus[]" value="Kegiatan organisasi">
                <label for="fc2" class="tag-label">🏢 Organisasi</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="fc3" name="next_week_focus[]" value="Skill development">
                <label for="fc3" class="tag-label">🚀 Development</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="fc4" name="next_week_focus[]" value="Self care">
                <label for="fc4" class="tag-label">💆 Self care</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="fc5" name="next_week_focus[]" value="Ibadah">
                <label for="fc5" class="tag-label">🕌 Ibadah</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="fc6" name="next_week_focus[]" value="Project">
                <label for="fc6" class="tag-label">🎨 Project</label>
            </div>
        </div>
        
        <label for="tujuan_spesifik">Target spesifik minggu depan:</label>
        <textarea id="tujuan_spesifik" name="tujuan_spesifik" required placeholder="Contoh: Selesaikan laporan PKM pada Jumat sore"></textarea>
        
        <hr class="section-divider">
        <h3>💬 Diskusi dengan Mentor</h3>
        <label for="topik_diskusi">Apa yang ingin kamu bahas?</label>
        <textarea id="topik_diskusi" name="topik_diskusi" required placeholder="Pertanyaan atau topik untuk sesi mentoring"></textarea>
        
        <p class="helper-text spaced">Bantuan yang dibutuhkan:</p>
        <div class="tags-grid">
            <div class="tag-item">
                <input type="checkbox" id="hp1" name="mentor_help[]" value="Brainstorming">
                <label for="hp1" class="tag-label">💭 Brainstorm</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="hp2" name="mentor_help[]" value="Feedback">
                <label for="hp2" class="tag-label">📝 Feedback</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="hp3" name="mentor_help[]" value="Solusi masalah">
                <label for="hp3" class="tag-label">🔧 Solusi</label>
            </div>
            <div class="tag-item">
                <input type="checkbox" id="hp4" name="mentor_help[]" value="Motivasi">
                <label for="hp4" class="tag-label">🔥 Motivasi</label>
            </div>
        </div>
        
        <label for="catatan_lain">Catatan tambahan (opsional):</label>
        <textarea id="catatan_lain" name="catatan_lain" placeholder="Ada yang mau ditambahkan?"></textarea>
        
        <button type="submit">🚀 Submit Refleksi</button>
    </form>

    <hr class="section-divider">
    <div class="riwayat" id="tabel-riwayat">
        <h2>Riwayat Refleksi</h2>
        <div class="table-responsive-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Nama Mentee</th>
                        <th>Tanggal Submit</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php if ($row['nama_disamarkan']) { echo "<i>Mode Privasi</i> 🔒"; } else { echo htmlspecialchars($row['nama_mentee']); } ?></td>
                                <td><?php echo date('d M Y H:i', strtotime($row['tanggal_submit'])); ?></td>
                                <td><a href="detail.php?id=<?php echo $row['id']; ?>">Lihat Detail</a></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="table-empty">Belum ada refleksi yang disubmit.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// ========== AUTO-SAVE PROGRESS SYSTEM ==========
const STORAGE_KEY = 'refleksi_interactive_progress';
const SERVER_PROGRESS_SHOULD_RESTORE = Boolean(window.SERVER_PROGRESS_SHOULD_RESTORE);
const SERVER_PROGRESS_DATA = window.SERVER_PROGRESS_DATA || null;

if (SERVER_PROGRESS_SHOULD_RESTORE && SERVER_PROGRESS_DATA) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(SERVER_PROGRESS_DATA));
        console.info('Progress dipulihkan dari server setelah kegagalan submit.');
    } catch (err) {
        console.warn('Gagal menyimpan ulang progress dari server:', err);
    }
}

// Function to save form data to localStorage
function saveProgress() {
    const formData = {
        nama_mentee: document.getElementById('nama_mentee').value,
        nama_mentor: document.getElementById('nama_mentor').value,
        samarkan_nama: document.getElementById('samarkan_nama').checked,
        periode_minggu: document.getElementById('periode_minggu').value,
        periode_tanggal: document.getElementById('periode_tanggal').value,
        mood_level: document.getElementById('mood-slider').value,
        energy_level: document.getElementById('energy-slider').value,
        stress_level: document.getElementById('stress-slider').value,
        pencapaian_text: document.getElementById('pencapaian_text').value,
        tantangan_text: document.getElementById('tantangan_text').value,
        pembelajaran_text: document.getElementById('pembelajaran_text').value,
        momen_berkesan: document.getElementById('momen_berkesan').value,
        tujuan_spesifik: document.getElementById('tujuan_spesifik').value,
        topik_diskusi: document.getElementById('topik_diskusi').value,
        catatan_lain: document.getElementById('catatan_lain').value,
        achievement_tags: getCheckedValues('achievement_tags[]'),
        challenge_tags: getCheckedValues('challenge_tags[]'),
        learning_tags: getCheckedValues('learning_tags[]'),
        next_week_focus: getCheckedValues('next_week_focus[]'),
        mentor_help: getCheckedValues('mentor_help[]'),
        timestamp: new Date().toISOString()
    };
    
    localStorage.setItem(STORAGE_KEY, JSON.stringify(formData));
    console.log('✅ Progress saved at ' + new Date().toLocaleTimeString());
}

// Function to get checked checkbox values
function getCheckedValues(name) {
    return Array.from(document.querySelectorAll(`input[name="${name}"]:checked`))
        .map(cb => cb.value);
}

// Function to restore form data from localStorage
function restoreProgress() {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (!saved) return false;
    
    try {
        const formData = JSON.parse(saved);
        
        // Restore text inputs
        if (formData.nama_mentee) document.getElementById('nama_mentee').value = formData.nama_mentee;
        if (formData.nama_mentor) document.getElementById('nama_mentor').value = formData.nama_mentor;
        if (formData.periode_minggu) document.getElementById('periode_minggu').value = formData.periode_minggu;
        if (formData.periode_tanggal) document.getElementById('periode_tanggal').value = formData.periode_tanggal;
        if (formData.pencapaian_text) document.getElementById('pencapaian_text').value = formData.pencapaian_text;
        if (formData.tantangan_text) document.getElementById('tantangan_text').value = formData.tantangan_text;
        if (formData.pembelajaran_text) document.getElementById('pembelajaran_text').value = formData.pembelajaran_text;
        if (formData.momen_berkesan) document.getElementById('momen_berkesan').value = formData.momen_berkesan;
        if (formData.tujuan_spesifik) document.getElementById('tujuan_spesifik').value = formData.tujuan_spesifik;
        if (formData.topik_diskusi) document.getElementById('topik_diskusi').value = formData.topik_diskusi;
        if (formData.catatan_lain) document.getElementById('catatan_lain').value = formData.catatan_lain;
        
        // Restore checkbox
        if (formData.samarkan_nama) {
            document.getElementById('samarkan_nama').checked = true;
            document.getElementById('kolom_sandi').style.display = 'block';
        }
        
        // Restore sliders
        if (formData.mood_level) {
            document.getElementById('mood-slider').value = formData.mood_level;
            document.getElementById('mood-slider').dispatchEvent(new Event('input'));
        }
        if (formData.energy_level) {
            document.getElementById('energy-slider').value = formData.energy_level;
            document.getElementById('energy-slider').dispatchEvent(new Event('input'));
        }
        if (formData.stress_level) {
            document.getElementById('stress-slider').value = formData.stress_level;
            document.getElementById('stress-slider').dispatchEvent(new Event('input'));
        }
        
        // Restore checkboxes
        restoreCheckboxes('achievement_tags[]', formData.achievement_tags);
        restoreCheckboxes('challenge_tags[]', formData.challenge_tags);
        restoreCheckboxes('learning_tags[]', formData.learning_tags);
        restoreCheckboxes('next_week_focus[]', formData.next_week_focus);
        restoreCheckboxes('mentor_help[]', formData.mentor_help);
        
        const savedTime = new Date(formData.timestamp).toLocaleString('id-ID');
        console.log('✅ Progress restored from ' + savedTime);
        
        // Show notification
        showNotification('📥 Progress terakhir dimuat! (Tersimpan: ' + savedTime + ')');
        return true;
    } catch (e) {
        console.error('Error restoring progress:', e);
        return false;
    }
}

// Function to restore checkbox values
function restoreCheckboxes(name, values) {
    if (!values || !Array.isArray(values)) return;
    values.forEach(value => {
        const checkbox = document.querySelector(`input[name="${name}"][value="${value}"]`);
        if (checkbox) checkbox.checked = true;
    });
}

// Function to clear saved progress
function clearProgress() {
    if (confirm('🗑️ Hapus progress yang tersimpan?')) {
        localStorage.removeItem(STORAGE_KEY);
        showNotification('✅ Progress dihapus!');
        location.reload();
    }
}

// Show notification
function showNotification(message) {
    const notification = document.createElement('div');
    notification.className = 'toast';
    notification.textContent = message;
    notification.style.opacity = '0';
    notification.style.transform = 'translateY(-10px)';
    document.body.appendChild(notification);

    requestAnimationFrame(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateY(0)';
    });

    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateY(-10px)';
        setTimeout(() => notification.remove(), 300);
    }, 1500);
}

if (SERVER_PROGRESS_SHOULD_RESTORE) {
    showNotification('⚠️ AI gagal, progress dipulihkan otomatis.');
}

if (window.SUBMIT_SUCCESS) {
    try {
        localStorage.removeItem(STORAGE_KEY);
    } catch (err) {
        console.warn('Gagal membersihkan progress selesai:', err);
    }
    showNotification('🎉 Refleksi terkirim! Menuju dashboard...');
    setTimeout(() => {
        window.location.href = 'index.php?submitted=success';
    }, 1600);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Try to restore saved progress
    const hasProgress = localStorage.getItem(STORAGE_KEY);
    if (hasProgress) {
        restoreProgress();
    }
    
    // Auto-save every 10 seconds
    setInterval(saveProgress, 10000);
    
    // Save on form input change
    const form = document.querySelector('form');
    form.addEventListener('input', function() {
        clearTimeout(window.autoSaveTimeout);
        window.autoSaveTimeout = setTimeout(saveProgress, 2000);
    });
    
    // Clear progress on successful submit
    form.addEventListener('submit', function() {
        localStorage.removeItem(STORAGE_KEY);
    });
});

// ========== SLIDER INTERACTIONS ==========
// Slider interactions
const moodEmojis = ['😢', '😕', '😐', '🙂', '😊', '😁', '🤩', '🥳', '🤗', '🎉'];
const energyEmojis = ['😴', '🥱', '😑', '😌', '🙂', '😊', '😃', '💪', '🔥', '⚡'];
const stressEmojis = ['😌', '😇', '🙂', '😐', '😅', '😰', '😓', '😫', '😩', '🤯'];

function updateSlider(sliderId, emojiId, valueId, emojis) {
    const slider = document.getElementById(sliderId);
    const emoji = document.getElementById(emojiId);
    const value = document.getElementById(valueId);
    
    slider.addEventListener('input', function() {
        const val = parseInt(this.value);
        value.textContent = val;
        emoji.textContent = emojis[val - 1];
        emoji.style.transform = 'scale(1.2)';
        setTimeout(() => emoji.style.transform = 'scale(1)', 200);
    });
}

updateSlider('mood-slider', 'mood-emoji', 'mood-value', moodEmojis);
updateSlider('energy-slider', 'energy-emoji', 'energy-value', energyEmojis);
updateSlider('stress-slider', 'stress-emoji', 'stress-value', stressEmojis);

// Privacy checkbox
document.getElementById('samarkan_nama').addEventListener('change', function() {
    document.getElementById('kolom_sandi').style.display = this.checked ? 'block' : 'none';
    document.getElementById('sandi_nama').required = this.checked;
});
</script>

</body>
</html>
