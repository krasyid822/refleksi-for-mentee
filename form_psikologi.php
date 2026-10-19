<?php
require_once 'env.php';

$pesan_sukses = '';
$pesan_error = '';
$redirect_after_success = false;

$input_values = [
    'nama_mentee' => '',
    'nama_mentor' => '',
    'samarkan_nama' => false,
    'sandi_nama' => '',
    'periode_minggu' => '',
    'periode_tanggal' => '',
    'mood_level' => 5,
    'fokus_level' => 5,
    'pulih_level' => 5,
    'suasana_hati' => '',
    'reaksi_tekanan' => '',
    'kebutuhan_utama' => '',
    'pemicu_utama' => '',
    'cerita_singkat' => '',
    'langkah_kecil' => '',
    'topik_diskusi' => '',
    'bantuan_diharapkan' => [],
    'catatan_lain' => '',
    'model_ai' => '',
    'kalimat_epik_client' => ''
];

$suasana_options = ['Tenang', 'Campur aduk', 'Cemas', 'Lelah', 'Bersemangat'];
$reaksi_options = ['Berdiam dulu', 'Cerita ke orang terdekat', 'Menyusun daftar langkah', 'Ibadah atau meditasi', 'Olahraga atau bergerak'];
$kebutuhan_options = ['Istirahat', 'Dukungan', 'Kejelasan', 'Motivasi', 'Ruang sendiri'];
$bantuan_options = ['Didengarkan dulu', 'Feedback lembut', 'Bantu susun prioritas', 'Dorongan positif', 'Doa dan dukungan'];

$input_values['model_ai'] = '';

if (defined('OPENROUTER_API_KEY') && !empty(OPENROUTER_API_KEY)) {
    $input_values['model_ai'] = '';
}

$riwayat_sql = "SELECT id, nama_mentee, nama_disamarkan, periode_minggu, periode_tanggal, tanggal_submit FROM refleksi_mingguan ORDER BY tanggal_submit DESC";
$riwayat_result = $conn->query($riwayat_sql);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($input_values as $key => $value) {
        if (isset($_POST[$key])) {
            $input_values[$key] = $_POST[$key];
        }
    }
    $input_values['samarkan_nama'] = isset($_POST['samarkan_nama']);

    $model_ai = trim($_POST['model_ai'] ?? '');
    $kalimat_epik = trim($_POST['kalimat_epik_client'] ?? '');

    if ($model_ai !== '' && !preg_match('/^.+:free$/', $model_ai)) {
        $pesan_error = 'Model AI tidak valid. Pilih model gratis dengan akhiran :free.';
    } elseif ($model_ai === '' || $kalimat_epik === '') {
        $pesan_error = 'Kalimat epik belum digenerate. Silakan pilih model AI gratis lalu klik tombol Generate & Kirim Check-In terlebih dahulu.';
    } else {

        $nama_mentee = $_POST['nama_mentee'];
        $nama_mentor = $_POST['nama_mentor'];
        $nama_disamarkan = isset($_POST['samarkan_nama']) ? 1 : 0;
        $sandi_nama = ($nama_disamarkan == 1 && !empty($_POST['sandi_nama'])) ? $_POST['sandi_nama'] : null;

        $periode_minggu_raw = trim($_POST['periode_minggu'] ?? '');
        $periode_minggu = $periode_minggu_raw === '' ? null : (int)$periode_minggu_raw;
        $periode_tanggal = $_POST['periode_tanggal'];

        $mood_level = max(1, min(10, (int)($_POST['mood_level'] ?? 5)));
        $fokus_level = max(1, min(10, (int)($_POST['fokus_level'] ?? 5)));
        $pulih_level = max(1, min(10, (int)($_POST['pulih_level'] ?? 5)));
        $skor_energi = (int)round(($mood_level + $fokus_level + $pulih_level) / 3);

        $suasana_hati = $_POST['suasana_hati'] ?? '';
        $reaksi_tekanan = $_POST['reaksi_tekanan'] ?? '';
        $kebutuhan_utama = $_POST['kebutuhan_utama'] ?? '';
        $pemicu_utama = $_POST['pemicu_utama'] ?? '';
        $cerita_singkat = $_POST['cerita_singkat'] ?? '';
        $langkah_kecil = $_POST['langkah_kecil'] ?? '';
        $topik_diskusi = $_POST['topik_diskusi'] ?? '';
        $bantuan_diharapkan = isset($_POST['bantuan_diharapkan']) ? implode(', ', $_POST['bantuan_diharapkan']) : '';
        $catatan_lain = $_POST['catatan_lain'] ?? '';
        $catatan_dengan_model = trim($catatan_lain . "\n[Model AI: " . $model_ai . "]");

        $pencapaian = 'Hasil check-in psikologi singkat: suasana hati ' . $suasana_hati . ', kebutuhan utama ' . $kebutuhan_utama . '.';
        $kemajuan_tujuan = 'Reaksi saat tertekan: ' . $reaksi_tekanan . '. Skor batin rata-rata: ' . $skor_energi . '/10.';
        $tantangan = $pemicu_utama;
        $pembelajaran_baru = $cerita_singkat;
        $penjelasan_energi = 'Mood: ' . $mood_level . '/10, Fokus: ' . $fokus_level . '/10, Pulih: ' . $pulih_level . '/10';
        $momen_berkesan = $langkah_kecil;
        $prioritas_mingguan = $langkah_kecil;
        $tujuan_spesifik = $langkah_kecil;

        $sql = 'INSERT INTO refleksi_mingguan (nama_mentee, nama_mentor, nama_disamarkan, sandi_nama, periode_minggu, periode_tanggal, pencapaian, kemajuan_tujuan, tantangan, pembelajaran_baru, skor_energi, penjelasan_energi, momen_berkesan, prioritas_mingguan, tujuan_spesifik, topik_diskusi, bantuan_diharapkan, catatan_lain, kalimat_epik) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            'ssisisssssissssssss',
            $nama_mentee,
            $nama_mentor,
            $nama_disamarkan,
            $sandi_nama,
            $periode_minggu,
            $periode_tanggal,
            $pencapaian,
            $kemajuan_tujuan,
            $tantangan,
            $pembelajaran_baru,
            $skor_energi,
            $penjelasan_energi,
            $momen_berkesan,
            $prioritas_mingguan,
            $tujuan_spesifik,
            $topik_diskusi,
            $bantuan_diharapkan,
            $catatan_dengan_model,
            $kalimat_epik
        );

        if ($stmt->execute()) {
            $pesan_sukses = '🎉 Check-in psikologi berhasil disubmit! Kalimat epikmu sudah dibuat oleh AI.';
            $redirect_after_success = true;
        } else {
            $pesan_error = 'Data gagal disimpan. Error: ' . $stmt->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tes Psikologi Singkat - Refleksi Mentee</title>
    <meta name="theme-color" content="#0f766e">
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="icons/icon-192.svg">
    <link rel="apple-touch-icon" href="icons/icon-192.svg">
    <script src="audio-player.js" defer></script>
    <script src="pwa-register.js" defer></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600&family=Space+Grotesk:wght@400;500&display=swap');

        :root {
            --ink: #0f172a;
            --bg-main: #0f766e;
            --card: #f0fdfa;
            --accent: #fb923c;
            --accent-soft: #fde68a;
            --outline: #134e4a;
            --shadow: #083344;
            --muted: rgba(15, 23, 42, 0.72);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Space Grotesk', -apple-system, BlinkMacSystemFont, system-ui, sans-serif;
            background: radial-gradient(circle at top left, rgba(251, 191, 36, 0.2), transparent 32%),
                        radial-gradient(circle at top right, rgba(45, 212, 191, 0.22), transparent 30%),
                        var(--bg-main);
            color: #f8fafc;
            min-height: 100vh;
            padding: 32px 18px 72px;
        }

        .container {
            max-width: 980px;
            margin: 0 auto;
            background: var(--card);
            color: var(--ink);
            border-radius: 32px;
            border: 3px solid var(--outline);
            box-shadow: 18px 24px 0 var(--shadow);
            padding: 40px;
        }

        .nav-links {
            display: flex;
            gap: 12px;
            margin-bottom: 28px;
            flex-wrap: wrap;
        }

        .nav-link {
            flex: 1;
            min-width: 170px;
            text-align: center;
            padding: 14px 18px;
            border-radius: 999px;
            border: 3px solid var(--outline);
            background: #d9f99d;
            color: var(--ink);
            font-weight: 600;
            text-decoration: none;
            box-shadow: 6px 8px 0 var(--shadow);
            transition: transform 0.15s ease;
        }

        .nav-link:hover { transform: translateY(-2px); }
        .nav-link.active { background: var(--accent); }

        h1 {
            font-family: 'Fredoka', sans-serif;
            text-transform: uppercase;
            color: #0f766e;
            font-size: clamp(2rem, 5vw, 3.1rem);
            letter-spacing: 0.08em;
        }

        .subtitle {
            margin: 12px 0 18px;
            color: var(--muted);
            font-size: 1.02rem;
            line-height: 1.6;
        }

        .note {
            background: #ecfeff;
            border: 2px dashed var(--outline);
            border-radius: 20px;
            padding: 16px 18px;
            margin: 20px 0 6px;
            box-shadow: 6px 8px 0 #cdeff0;
        }

        .success-message,
        .error-message {
            border-radius: 22px;
            border: 3px solid var(--outline);
            padding: 16px 20px;
            font-weight: 600;
            margin: 18px 0 24px;
            box-shadow: 8px 10px 0 var(--shadow);
            text-align: center;
        }

        .success-message { background: #bbf7d0; color: #064e3b; }
        .error-message { background: #ffe4e6; color: #7f1d1d; }

        hr {
            border: none;
            height: 3px;
            background: var(--outline);
            margin: 34px 0 24px;
            border-radius: 999px;
            opacity: 0.15;
        }

        h3 {
            font-family: 'Fredoka', sans-serif;
            font-size: 1.25rem;
            margin-bottom: 8px;
            color: var(--ink);
        }

        label {
            display: block;
            margin-top: 18px;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--ink);
        }

        input[type="text"],
        input[type="number"],
        input[type="password"],
        textarea {
            width: 100%;
            border: 2px solid var(--outline);
            border-radius: 18px;
            padding: 14px 16px;
            font: inherit;
            background: #fff;
            color: var(--ink);
            box-shadow: 6px 8px 0 #b7e4e6;
        }

        textarea {
            min-height: 106px;
            resize: vertical;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #0f766e;
            box-shadow: 6px 8px 0 var(--shadow);
        }

        .privacy-box {
            border: 2px dashed var(--outline);
            border-radius: 20px;
            padding: 18px;
            margin-top: 16px;
            background: #f0fdff;
            box-shadow: 6px 8px 0 #cdeff0;
        }

        .privacy-toggle {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .privacy-toggle label {
            display: inline;
            margin: 0;
            font-weight: 500;
        }

        #kolom_sandi { display: none; margin-top: 14px; }

        .grid-choices {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }

        .choice-card {
            position: relative;
        }

        .choice-card input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .choice-card label {
            margin: 0;
            display: block;
            padding: 14px 16px;
            border-radius: 18px;
            border: 2px solid var(--outline);
            background: #fff;
            box-shadow: 6px 8px 0 #b7e4e6;
            text-align: center;
            cursor: pointer;
            transition: transform 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
        }

        .choice-card input:checked + label {
            background: var(--accent-soft);
            transform: translateY(-2px);
            box-shadow: 10px 12px 0 var(--shadow);
        }

        .slider-box {
            background: #fff;
            border: 2px solid var(--outline);
            border-radius: 22px;
            padding: 18px 20px;
            box-shadow: 6px 8px 0 #b7e4e6;
            margin: 14px 0;
        }

        .slider-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .slider-emoji { font-size: 2rem; }
        .slider-value { font-size: 1.3rem; font-weight: 700; color: #0f766e; }

        input[type="range"] {
            width: 100%;
            height: 10px;
            border-radius: 999px;
            background: linear-gradient(90deg, #a7f3d0, #fdba74);
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
            box-shadow: 4px 6px 0 var(--shadow);
            cursor: pointer;
        }

        .helper-text {
            color: var(--muted);
            margin-top: 6px;
            font-size: 0.95rem;
        }

        .checkbox-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }

        .checkbox-item {
            border: 2px solid var(--outline);
            border-radius: 18px;
            background: #fff;
            box-shadow: 6px 8px 0 #b7e4e6;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-item input { width: 18px; height: 18px; }
        .checkbox-item label { margin: 0; font-weight: 600; }

        .table-section {
            margin-top: 34px;
        }

        html { scroll-behavior: smooth; }

        .table-section h3 {
            margin-bottom: 10px;
        }

        .table-responsive-wrapper {
            margin-top: 16px;
            border: 3px solid var(--outline);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 12px 16px 0 #0b1445;
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

        .table-empty {
            text-align: center;
            padding: 18px 16px;
        }

        .ai-panel {
            margin-top: 24px;
            padding: 18px;
            border-radius: 22px;
            border: 2px solid var(--outline);
            background: #ecfeff;
            box-shadow: 8px 10px 0 #b7e4e6;
        }

        .ai-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 12px;
        }

        .ai-panel select {
            width: 100%;
            border: 2px solid var(--outline);
            border-radius: 18px;
            padding: 14px 16px;
            font: inherit;
            background: #fff;
            color: var(--ink);
            box-shadow: 6px 8px 0 #b7e4e6;
        }

        .ai-status {
            margin-top: 12px;
            font-size: 0.94rem;
            color: #134e4a;
        }

        .ai-generate {
            margin-top: 16px;
            width: 100%;
            border-radius: 14px;
            border: 2px solid var(--outline);
            background: #22c55e;
            color: #052e16;
            box-shadow: 6px 8px 0 #15803d;
            font-weight: 700;
            padding: 12px 14px;
            cursor: pointer;
        }

        .ai-generate:disabled { opacity: 0.6; cursor: not-allowed; }

        button {
            width: 100%;
            margin-top: 32px;
            padding: 16px 24px;
            border-radius: 999px;
            border: 3px solid var(--outline);
            background: var(--accent);
            color: #0f172a;
            font-weight: 700;
            font-size: 1.05rem;
            box-shadow: 12px 14px 0 var(--shadow);
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        button:hover { transform: translateY(-3px); box-shadow: 16px 18px 0 var(--shadow); }
        button:active { transform: translateY(0); box-shadow: 8px 10px 0 var(--shadow); }

        @media (max-width: 768px) {
            body { padding: 20px 12px 60px; }
            .container { padding: 28px 20px; box-shadow: 12px 18px 0 var(--shadow); }
            .nav-links { gap: 8px; margin-bottom: 22px; }
            .nav-link {
                flex: 1 1 calc(50% - 4px);
                min-width: 0;
                padding: 11px 10px;
                font-size: 0.9rem;
                line-height: 1.2;
                border-radius: 18px;
                box-shadow: 4px 6px 0 var(--shadow);
            }
            .nav-link:last-child { flex-basis: 100%; }
            .grid-choices,
            .checkbox-grid { grid-template-columns: 1fr; }
            .ai-panel { padding: 16px; }
            table { min-width: 620px; }
            .table-responsive-wrapper { overflow-x: auto; }
        }
    </style>
    <script>
        window.SUBMIT_SUCCESS = <?php echo $redirect_after_success ? 'true' : 'false'; ?>;
        window.APP_OPENROUTER_API_KEY = <?php echo json_encode(defined('OPENROUTER_API_KEY') ? OPENROUTER_API_KEY : ''); ?>;
    </script>
</head>
<body>
<div class="container">
    <div class="nav-links">
        <a href="index.php" class="nav-link">🏠 Dashboard</a>
        <a href="form.php" class="nav-link">📝 Form Klasik</a>
        <a href="form_interactive.php" class="nav-link">✨ Form Interaktif</a>
        <a href="form_psikologi.php" class="nav-link active">🧠 Tes Psikologi</a>
        <a href="#tabel-riwayat" class="nav-link">🔎 Detail</a>
    </div>

    <h1>Tes Psikologi Singkat</h1>
    <p class="subtitle">Form ini bukan alat diagnosis. Ini hanya check-in singkat untuk membantu mentee membaca kondisi batin, lalu merangkumnya menjadi kalimat refleksi yang hangat dan realistis.</p>

    <div class="note">
        Isi dengan jujur dan seperlunya. Kalau ada tanda stres berat atau kondisi yang terasa mengganggu, tetap utamakan bantuan profesional atau dukungan orang terdekat.
    </div>

    <?php if (!empty($pesan_sukses)): ?>
        <div class="success-message"><?php echo htmlspecialchars($pesan_sukses); ?></div>
    <?php endif; ?>
    <?php if (!empty($pesan_error)): ?>
        <div class="error-message"><?php echo $pesan_error; ?></div>
    <?php endif; ?>

    <form action="form_psikologi.php" method="post" id="form-psikologi">
        <h3>👤 Info Dasar</h3>
        <label for="nama_mentee">Nama Mentee</label>
        <input type="text" id="nama_mentee" name="nama_mentee" required value="<?php echo htmlspecialchars($input_values['nama_mentee']); ?>">

        <label for="nama_mentor">Nama Mentor</label>
        <input type="text" id="nama_mentor" name="nama_mentor" required value="<?php echo htmlspecialchars($input_values['nama_mentor']); ?>">

        <div class="privacy-box">
            <div class="privacy-toggle">
                <input type="checkbox" id="samarkan_nama" name="samarkan_nama" value="1" <?php echo $input_values['samarkan_nama'] ? 'checked' : ''; ?>>
                <label for="samarkan_nama">🔒 Samarkan nama</label>
            </div>
            <div id="kolom_sandi">
                <label for="sandi_nama">Sandi</label>
                <input type="password" id="sandi_nama" name="sandi_nama" value="<?php echo htmlspecialchars($input_values['sandi_nama']); ?>">
            </div>
        </div>

        <label for="periode_minggu">Minggu ke- <span style="font-weight:400; color: var(--muted);">(opsional)</span></label>
        <input type="number" id="periode_minggu" name="periode_minggu" min="1" value="<?php echo htmlspecialchars($input_values['periode_minggu']); ?>" placeholder="Kosongkan jika tidak ingat">

        <label for="periode_tanggal">Tanggal Periode</label>
        <input type="text" id="periode_tanggal" name="periode_tanggal" required placeholder="12 Agu - 18 Agu 2025" value="<?php echo htmlspecialchars($input_values['periode_tanggal']); ?>">

        <hr>
        <h3>🧠 Mini Check-In</h3>
        <p class="helper-text">Pilih yang paling mendekati kondisi kamu sekarang.</p>

        <label>Suasana hati hari ini</label>
        <div class="grid-choices">
            <?php foreach ($suasana_options as $option): ?>
                <div class="choice-card">
                    <input type="radio" id="suasana_<?php echo md5($option); ?>" name="suasana_hati" value="<?php echo htmlspecialchars($option); ?>" <?php echo ($input_values['suasana_hati'] === $option) ? 'checked' : ''; ?>>
                    <label for="suasana_<?php echo md5($option); ?>"><?php echo htmlspecialchars($option); ?></label>
                </div>
            <?php endforeach; ?>
        </div>

        <label>Cara kamu menjawab tekanan</label>
        <div class="grid-choices">
            <?php foreach ($reaksi_options as $option): ?>
                <div class="choice-card">
                    <input type="radio" id="reaksi_<?php echo md5($option); ?>" name="reaksi_tekanan" value="<?php echo htmlspecialchars($option); ?>" <?php echo ($input_values['reaksi_tekanan'] === $option) ? 'checked' : ''; ?>>
                    <label for="reaksi_<?php echo md5($option); ?>"><?php echo htmlspecialchars($option); ?></label>
                </div>
            <?php endforeach; ?>
        </div>

        <label>Kebutuhan utama saat ini</label>
        <div class="grid-choices">
            <?php foreach ($kebutuhan_options as $option): ?>
                <div class="choice-card">
                    <input type="radio" id="butuh_<?php echo md5($option); ?>" name="kebutuhan_utama" value="<?php echo htmlspecialchars($option); ?>" <?php echo ($input_values['kebutuhan_utama'] === $option) ? 'checked' : ''; ?>>
                    <label for="butuh_<?php echo md5($option); ?>"><?php echo htmlspecialchars($option); ?></label>
                </div>
            <?php endforeach; ?>
        </div>

        <hr>
        <h3>📊 Skor Cepat</h3>

        <div class="slider-box">
            <div class="slider-head">
                <strong>Mood</strong>
                <span class="slider-emoji" id="mood-emoji">😊</span>
                <span class="slider-value" id="mood-value">5</span>
            </div>
            <input type="range" id="mood-slider" name="mood_level" min="1" max="10" value="<?php echo htmlspecialchars($input_values['mood_level']); ?>">
        </div>

        <div class="slider-box">
            <div class="slider-head">
                <strong>Fokus</strong>
                <span class="slider-emoji" id="fokus-emoji">😊</span>
                <span class="slider-value" id="fokus-value">5</span>
            </div>
            <input type="range" id="fokus-slider" name="fokus_level" min="1" max="10" value="<?php echo htmlspecialchars($input_values['fokus_level']); ?>">
        </div>

        <div class="slider-box">
            <div class="slider-head">
                <strong>Rasa Pulih</strong>
                <span class="slider-emoji" id="pulih-emoji">😊</span>
                <span class="slider-value" id="pulih-value">5</span>
            </div>
            <input type="range" id="pulih-slider" name="pulih_level" min="1" max="10" value="<?php echo htmlspecialchars($input_values['pulih_level']); ?>">
        </div>

        <hr>
        <h3>🪞 Cerita Singkat</h3>
        <label for="pemicu_utama">Apa pemicu utama yang paling terasa minggu ini?</label>
        <textarea id="pemicu_utama" name="pemicu_utama" required placeholder="Contoh: tugas menumpuk, kurang tidur, konflik kecil, atau rasa cemas yang datang mendadak..."><?php echo htmlspecialchars($input_values['pemicu_utama']); ?></textarea>

        <label for="cerita_singkat">Jelaskan kondisi batinmu dengan singkat</label>
        <textarea id="cerita_singkat" name="cerita_singkat" required placeholder="Tulis ringkasan singkat tentang apa yang kamu rasakan minggu ini..."><?php echo htmlspecialchars($input_values['cerita_singkat']); ?></textarea>

        <label for="langkah_kecil">Langkah kecil realistis yang ingin kamu lakukan</label>
        <textarea id="langkah_kecil" name="langkah_kecil" required placeholder="Contoh: tidur lebih awal, membatasi distraksi, atau ngobrol dengan orang terpercaya..."><?php echo htmlspecialchars($input_values['langkah_kecil']); ?></textarea>

        <hr>
        <h3>💬 Diskusi dengan Mentor</h3>
        <label for="topik_diskusi">Topik apa yang ingin kamu bahas dengan mentor?</label>
        <textarea id="topik_diskusi" name="topik_diskusi" required placeholder="Apa yang ingin didengar, ditanyakan, atau dibantu oleh mentor?"><?php echo htmlspecialchars($input_values['topik_diskusi']); ?></textarea>

        <label>Bantuan yang diharapkan</label>
        <div class="checkbox-grid">
            <?php foreach ($bantuan_options as $option): ?>
                <div class="checkbox-item">
                    <input type="checkbox" id="bantuan_<?php echo md5($option); ?>" name="bantuan_diharapkan[]" value="<?php echo htmlspecialchars($option); ?>" <?php echo in_array($option, $input_values['bantuan_diharapkan'], true) ? 'checked' : ''; ?>>
                    <label for="bantuan_<?php echo md5($option); ?>"><?php echo htmlspecialchars($option); ?></label>
                </div>
            <?php endforeach; ?>
        </div>

        <label for="catatan_lain">Catatan tambahan</label>
        <textarea id="catatan_lain" name="catatan_lain" placeholder="Tambahkan hal lain yang ingin kamu sampaikan..."><?php echo htmlspecialchars($input_values['catatan_lain']); ?></textarea>

        <div class="ai-panel">
            <h3>🤖 Generate Kalimat Epik (OpenRouter)</h3>
            <p class="helper-text">API key aplikasi digunakan otomatis dari konfigurasi. Daftar model dimuat dari OpenRouter dan difilter hanya yang gratis.</p>

            <div class="ai-row">
                <div>
                    <label for="model_ai">Model AI</label>
                    <select id="model_ai" name="model_ai" required>
                        <option value="">Memuat model gratis...</option>
                    </select>
                </div>
            </div>

            <button type="button" id="btn-generate-ai" class="ai-generate">⚡ Generate & Kirim Check-In</button>
            <div id="ai-status" class="ai-status">Belum digenerate.</div>
            <input type="hidden" id="kalimat_epik_client" name="kalimat_epik_client" value="<?php echo htmlspecialchars($input_values['kalimat_epik_client']); ?>">
        </div>

    </form>

    <div class="table-section" id="tabel-riwayat">
        <h3>📚 Riwayat Hasil Sebelumnya</h3>
        <p class="helper-text">Daftar hasil check-in psikologi yang sudah dibuat sebelumnya.</p>
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
                    <?php if ($riwayat_result && $riwayat_result->num_rows > 0): ?>
                        <?php while ($row = $riwayat_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php
                                    if ($row['nama_disamarkan']) {
                                        echo '<i>Mentee Anonim</i> 🔒';
                                    } else {
                                        echo htmlspecialchars($row['nama_mentee']);
                                    }
                                    ?>
                                </td>
                                <td><?php echo date('d M Y H:i', strtotime($row['tanggal_submit'])); ?></td>
                                <td><a href="detail.php?id=<?php echo $row['id']; ?>">Lihat Detail</a></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="table-empty">Belum ada riwayat hasil psikologi yang tersimpan.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script type="module">

function toNumber(value, fallback) {
    const parsed = parseInt(value, 10);
    return Number.isNaN(parsed) ? fallback : parsed;
}

function buildPromptFromForm() {
    const getValue = (id) => document.getElementById(id)?.value?.trim() || '';
    const getCheckedRadio = (name) => document.querySelector(`input[name="${name}"]:checked`)?.value || '';
    const checkedHelp = Array.from(document.querySelectorAll('input[name="bantuan_diharapkan[]"]:checked')).map((item) => item.value).join(', ');

    const mood = toNumber(getValue('mood-slider'), 5);
    const fokus = toNumber(getValue('fokus-slider'), 5);
    const pulih = toNumber(getValue('pulih-slider'), 5);

    return (
`Berdasarkan check-in psikologi singkat seorang mentee berikut, buatkan satu kalimat epik dalam Bahasa Indonesia yang hangat, reflektif, tidak menghakimi, tanpa diagnosis dan tanpa nasihat medis. Kalimat harus singkat dan inspiratif.\n\n` +
`Suasana Hati: ${getCheckedRadio('suasana_hati') || '-'}\n` +
`Cara Menjawab Tekanan: ${getCheckedRadio('reaksi_tekanan') || '-'}\n` +
`Kebutuhan Utama: ${getCheckedRadio('kebutuhan_utama') || '-'}\n` +
`Skor Mood: ${mood}/10\n` +
`Skor Fokus: ${fokus}/10\n` +
`Skor Pulih: ${pulih}/10\n` +
`Pemicu Utama: ${getValue('pemicu_utama') || '-'}\n` +
`Cerita Singkat: ${getValue('cerita_singkat') || '-'}\n` +
`Langkah Kecil: ${getValue('langkah_kecil') || '-'}\n` +
`Topik Diskusi Mentor: ${getValue('topik_diskusi') || '-'}\n` +
`Bantuan Diharapkan: ${checkedHelp || '-'}\n` +
`Catatan Tambahan: ${getValue('catatan_lain') || '-'}\n`
    );
}

const btnGenerateAi = document.getElementById('btn-generate-ai');
const modelSelect = document.getElementById('model_ai');
const aiStatus = document.getElementById('ai-status');
const epicHidden = document.getElementById('kalimat_epik_client');
const form = document.getElementById('form-psikologi');
const freeModelFallback = [
    'meta-llama/llama-3.1-8b-instruct:free',
    'google/gemma-2-9b-it:free',
    'qwen/qwen-2.5-7b-instruct:free',
    'mistralai/mistral-7b-instruct:free'
];

async function loadFreeModels() {
    const selectedValue = <?php echo json_encode($input_values['model_ai']); ?>;
    const defaultChoice = selectedValue || '';

    try {
        const response = await fetch('https://openrouter.ai/api/v1/models', {
            headers: {
                'HTTP-Referer': window.location.origin,
                'X-Title': 'Refleksi Mentee'
            }
        });

        const data = await response.json();
        const freeModels = Array.isArray(data?.data)
            ? data.data.filter((item) => typeof item?.id === 'string' && item.id.endsWith(':free'))
            : [];

        const modelsToShow = freeModels.length > 0 ? freeModels : freeModelFallback.map((id) => ({ id }));

        modelSelect.innerHTML = '<option value="">Pilih model gratis</option>';
        modelsToShow.forEach((item) => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.id;
            if (item.id === defaultChoice) {
                option.selected = true;
            }
            modelSelect.appendChild(option);
        });

        if (!modelSelect.value && modelsToShow.length > 0) {
            modelSelect.value = modelsToShow[0].id;
        }
    } catch (error) {
        modelSelect.innerHTML = '<option value="">Gagal memuat model gratis</option>';
        aiStatus.textContent = 'Gagal memuat daftar model OpenRouter: ' + (error?.message || String(error));
        apiStatusError();
    }
}

loadFreeModels();

modelSelect.addEventListener('change', function () {
    aiStatus.textContent = this.value ? 'Model dipilih: ' + this.value : 'Belum digenerate.';
});

btnGenerateAi.addEventListener('click', async function () {
    const apiKey = (window.APP_OPENROUTER_API_KEY || '').trim();
    const model = modelSelect.value;

    if (!apiKey) {
        aiStatus.textContent = 'API key OpenRouter belum terpasang di file .env.';
        apiStatusError();
        return;
    }

    if (!model || !model.endsWith(':free')) {
        aiStatus.textContent = 'Pilih model gratis dari daftar OpenRouter terlebih dahulu.';
        apiStatusError();
        return;
    }

    btnGenerateAi.disabled = true;
    aiStatus.textContent = 'Sedang menghubungi OpenRouter...';
    apiStatusInfo();

    try {
        const prompt = buildPromptFromForm();

        const response = await fetch('https://openrouter.ai/api/v1/chat/completions', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + apiKey,
                'Content-Type': 'application/json',
                'HTTP-Referer': window.location.origin,
                'X-Title': 'Refleksi Mentee'
            },
            body: JSON.stringify({
                model: model,
                messages: [
                    { role: 'system', content: 'Kamu menulis satu kalimat epik yang singkat, hangat, dan reflektif dalam Bahasa Indonesia.' },
                    { role: 'user', content: prompt }
                ]
            })
        });

        const result = await response.json();
        const text = (result?.choices?.[0]?.message?.content || '').trim();
        if (!text) {
            throw new Error(result?.error?.message || 'Respons kosong dari OpenRouter.');
        }

        epicHidden.value = text;
        aiStatus.textContent = 'Kalimat epik berhasil digenerate dengan model ' + model + '.';
        apiStatusSuccess();

        try {
            form.requestSubmit();
        } catch (submitError) {
            form.submit();
        }
    } catch (error) {
        epicHidden.value = '';
        aiStatus.textContent = 'Gagal generate: ' + (error?.message || String(error));
        apiStatusError();
    } finally {
        btnGenerateAi.disabled = false;
    }
});

form.addEventListener('submit', function (event) {
    if (!epicHidden.value.trim()) {
        event.preventDefault();
        aiStatus.textContent = 'Generate kalimat epik dulu sebelum mengirim check-in.';
        apiStatusError();
        return;
    }
    if (!modelSelect.value) {
        event.preventDefault();
        aiStatus.textContent = 'Pilih model AI terlebih dahulu.';
        apiStatusError();
    }
});

function apiStatusInfo() {
    aiStatus.style.color = '#134e4a';
}

function apiStatusSuccess() {
    aiStatus.style.color = '#065f46';
}

function apiStatusError() {
    aiStatus.style.color = '#b91c1c';
}

const moodEmojis = ['😢', '😕', '😐', '🙂', '😊', '😁', '🤩', '🥳', '🤗', '🎉'];
const fokusEmojis = ['😵', '😓', '😶', '😌', '🙂', '😊', '😃', '💪', '🔥', '⚡'];
const pulihEmojis = ['🪫', '🥱', '😑', '😌', '🙂', '😊', '😃', '🌿', '✨', '🫶'];

function updateSlider(sliderId, emojiId, valueId, emojis) {
    const slider = document.getElementById(sliderId);
    const emoji = document.getElementById(emojiId);
    const value = document.getElementById(valueId);

    function refresh() {
        const parsedValue = parseInt(slider.value, 10);
        value.textContent = parsedValue;
        emoji.textContent = emojis[parsedValue - 1];
    }

    slider.addEventListener('input', refresh);
    refresh();
}

updateSlider('mood-slider', 'mood-emoji', 'mood-value', moodEmojis);
updateSlider('fokus-slider', 'fokus-emoji', 'fokus-value', fokusEmojis);
updateSlider('pulih-slider', 'pulih-emoji', 'pulih-value', pulihEmojis);

const privacyToggle = document.getElementById('samarkan_nama');
const kolomSandi = document.getElementById('kolom_sandi');
const inputSandi = document.getElementById('sandi_nama');

function syncPrivacyField() {
    if (privacyToggle.checked) {
        kolomSandi.style.display = 'block';
        inputSandi.required = true;
    } else {
        kolomSandi.style.display = 'none';
        inputSandi.required = false;
    }
}

privacyToggle.addEventListener('change', syncPrivacyField);
syncPrivacyField();

if (window.SUBMIT_SUCCESS) {
    setTimeout(function () {
        window.location.href = 'index.php?submitted=success';
    }, 1400);
}
</script>
</body>
</html>
