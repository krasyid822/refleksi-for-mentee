<?php
require_once 'env.php';
include 'gemini_helper.php';

$pesan_sukses = '';
$pesan_error = '';

// Inisialisasi variabel untuk menyimpan nilai input (untuk kasus gagal submit)
$input_values = [
    'nama_mentee' => '', 'nama_mentor' => '', 'samarkan_nama' => false, 'sandi_nama' => '',
    'periode_minggu' => '', 'periode_tanggal' => '', 'pencapaian' => '', 'kemajuan_tujuan' => '',
    'tantangan' => '', 'pembelajaran_baru' => '', 'skor_energi' => '', 'penjelasan_energi' => '',
    'momen_berkesan' => '', 'prioritas_mingguan' => '', 'tujuan_spesifik' => '', 'topik_diskusi' => '',
    'bantuan_diharapkan' => [], 'catatan_lain' => ''
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Simpan semua inputan ke dalam variabel, agar bisa ditampilkan kembali jika gagal
    foreach ($input_values as $key => $value) {
        if (isset($_POST[$key])) {
            $input_values[$key] = $_POST[$key];
        }
    }
    $input_values['samarkan_nama'] = isset($_POST['samarkan_nama']); // Khusus checkbox

    // 1. Kumpulkan data refleksi untuk AI
    $data_refleksi_ai = [
        'pencapaian' => $_POST['pencapaian'], 'kemajuan_tujuan' => $_POST['kemajuan_tujuan'], 'tantangan' => $_POST['tantangan'],
        'pembelajaran_baru' => $_POST['pembelajaran_baru'], 'skor_energi' => $_POST['skor_energi'], 'penjelasan_energi' => $_POST['penjelasan_energi'],
        'momen_berkesan' => $_POST['momen_berkesan'], 'prioritas_mingguan' => $_POST['prioritas_mingguan'], 'catatan_lain' => $_POST['catatan_lain']
    ];
    
    // 2. Panggil API Gemini TERLEBIH DAHULU
    $hasil_ai = panggilGemini($data_refleksi_ai);

    if (!$hasil_ai['sukses']) {
        $pesan_error = "PROSES SUBMIT DIBATALKAN. Kegagalan AI: " . htmlspecialchars($hasil_ai['error']) . " Data Anda di bawah ini aman dan tidak hilang, silakan coba submit lagi. Atau coba hubungi pembuat aplikasi ini.";
    } else {
        // 4. Jika AI berhasil, kumpulkan semua data lain dan lakukan INSERT
        $kalimat_epik = $hasil_ai['kalimat'];
        
        $nama_mentee = $_POST['nama_mentee']; $nama_mentor = $_POST['nama_mentor'];
        $nama_disamarkan = isset($_POST['samarkan_nama']) ? 1 : 0;
        $sandi_nama = ($nama_disamarkan == 1 && !empty($_POST['sandi_nama'])) ? $_POST['sandi_nama'] : null;
        if ($nama_disamarkan == 1 && $sandi_nama === null) $nama_disamarkan = 0;
        
        $periode_minggu_raw = trim($_POST['periode_minggu'] ?? '');
        $periode_minggu = $periode_minggu_raw === '' ? null : (int)$periode_minggu_raw;
        $periode_tanggal = $_POST['periode_tanggal']; $topik_diskusi = $_POST['topik_diskusi'];
        $bantuan_diharapkan = isset($_POST['bantuan_diharapkan']) ? implode(", ", $_POST['bantuan_diharapkan']) : '';
        $tujuan_spesifik = $_POST['tujuan_spesifik'];
        
        $sql = "INSERT INTO refleksi_mingguan (nama_mentee, nama_mentor, nama_disamarkan, sandi_nama, periode_minggu, periode_tanggal, pencapaian, kemajuan_tujuan, tantangan, pembelajaran_baru, skor_energi, penjelasan_energi, momen_berkesan, prioritas_mingguan, tujuan_spesifik, topik_diskusi, bantuan_diharapkan, catatan_lain, kalimat_epik) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssisisssssissssssss", $nama_mentee, $nama_mentor, $nama_disamarkan, $sandi_nama, $periode_minggu, $periode_tanggal, $data_refleksi_ai['pencapaian'], $data_refleksi_ai['kemajuan_tujuan'], $data_refleksi_ai['tantangan'], $data_refleksi_ai['pembelajaran_baru'], $data_refleksi_ai['skor_energi'], $data_refleksi_ai['penjelasan_energi'], $data_refleksi_ai['momen_berkesan'], $data_refleksi_ai['prioritas_mingguan'], $tujuan_spesifik, $topik_diskusi, $bantuan_diharapkan, $data_refleksi_ai['catatan_lain'], $kalimat_epik);

        if ($stmt->execute()) {
            $pesan_sukses = "Refleksi berhasil disubmit dan kalimat epik telah dibuat!";
            // Kosongkan form setelah sukses
            foreach ($input_values as $key => &$value) {
                $value = is_array($value) ? [] : '';
            }
            $input_values['samarkan_nama'] = false;

        } else {
            $pesan_error = "Data gagal disimpan ke database. Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

$riwayat_sql = "SELECT id, nama_mentee, nama_disamarkan, periode_minggu, periode_tanggal, tanggal_submit FROM refleksi_mingguan ORDER BY tanggal_submit DESC";
$result = $conn->query($riwayat_sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulir Refleksi Mingguan</title>
    <meta name="theme-color" content="#7f1d1d">
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="icons/icon-192.svg">
    <link rel="apple-touch-icon" href="icons/icon-192.svg">
    <script src="audio-player.js" defer></script>
    <script src="pwa-register.js" defer></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600&family=Space+Grotesk:wght@400;500&display=swap');

        :root {
            --ink: #23140f;
            --bg-main: #7f1d1d;
            --card: #fff6ef;
            --accent: #ffb703;
            --accent-dark: #5c1d18;
            --muted: rgba(35, 20, 15, 0.72);
            --outline: #5c1d18;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Space Grotesk', 'Poppins', -apple-system, BlinkMacSystemFont, system-ui, sans-serif;
            background: radial-gradient(circle at top left, rgba(255, 183, 3, 0.26), transparent 30%),
                        radial-gradient(circle at top right, rgba(255, 255, 255, 0.16), transparent 28%),
                        var(--bg-main);
            color: #f8fafc;
            min-height: 100vh;
            padding: 32px 18px 72px;
        }

        .container {
            max-width: 960px;
            margin: 0 auto;
            background: var(--card);
            color: var(--ink);
            border-radius: 32px;
            border: 3px solid var(--outline);
            box-shadow: 18px 24px 0 #4c1026;
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
            background: #ffe4c7;
            color: var(--ink);
            font-weight: 600;
            text-decoration: none;
            box-shadow: 6px 8px 0 #4c1026;
            transition: transform 0.15s ease;
        }

        .nav-link:hover { transform: translateY(-2px); }

        .nav-link.active {
            background: #fff;
            color: var(--accent-dark);
        }

        h1 {
            font-family: 'Fredoka', sans-serif;
            text-transform: uppercase;
            /* text-align: center; */
            color: #ffcf70;
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

        h3 {
            font-family: 'Fredoka', sans-serif;
            font-size: 1.25rem;
            margin-top: 22px;
            color: var(--ink);
        }

        hr {
            border: none;
            height: 3px;
            background: #4c1026;
            margin: 28px 0 18px;
            border-radius: 999px;
            opacity: 0.15;
        }

        label {
            font-weight: 600;
            margin-top: 14px;
            margin-bottom: 6px;
            display: block;
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
            box-shadow: 6px 8px 0 #0b1445;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .privacy-box {
            border: 2px dashed var(--outline);
            border-radius: 20px;
            padding: 18px;
            background: #fff1e6;
            margin-top: 16px;
            box-shadow: 6px 8px 0 #f4c7a7;
        }

        #kolom_sandi { display: none; margin-top: 14px; }

        .checkbox-group label {
            font-weight: 500;
            color: var(--ink);
            margin: 4px 0;
        }

        button {
            background: var(--accent);
            color: var(--accent-dark);
            border: 3px solid var(--outline);
            border-radius: 999px;
            padding: 16px 28px;
            font-weight: 700;
            font-size: 1.05rem;
            width: 100%;
            margin-top: 32px;
            box-shadow: 12px 14px 0 #0b1445;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        button:hover {
            transform: translateY(-3px);
            box-shadow: 16px 18px 0 #0b1445;
        }

        button:active {
            transform: translateY(0);
            box-shadow: 8px 10px 0 #0b1445;
        }

        .success-message,
        .error-message {
            border-radius: 24px;
            border: 3px solid var(--outline);
            padding: 16px 20px;
            font-weight: 600;
            margin-bottom: 24px;
            box-shadow: 8px 10px 0 #0b1445;
        }

        .success-message { background: #fef3c7; color: #7c2d12; }
        .error-message { background: #fee2e2; color: #7f1d1d; }

        .table-responsive-wrapper {
            margin-top: 18px;
            border: 3px solid var(--outline);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 12px 16px 0 #4c1026;
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
            background: #ffd6a5;
            font-family: 'Fredoka', sans-serif;
            text-transform: uppercase;
            font-size: 0.85rem;
        }

        tr:nth-child(even) { background: #fff8f1; }

        tr:hover { background: #fff0e1; }

        a {
            color: var(--accent-dark);
            font-weight: 600;
        }

        .toast {
            position: fixed;
            top: 24px;
            right: 24px;
            background: #fde68a;
            color: var(--accent-dark);
            border: 3px solid var(--outline);
            border-radius: 16px;
            padding: 14px 20px;
            box-shadow: 8px 12px 0 #4c1026;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            body { padding: 20px 12px 60px; }
            .container { padding: 28px 20px; box-shadow: 12px 18px 0 #4c1026; }
            .nav-links { gap: 8px; margin-bottom: 22px; }
            .nav-link {
                flex: 1 1 calc(50% - 4px);
                min-width: 0;
                padding: 11px 10px;
                font-size: 0.9rem;
                line-height: 1.2;
                border-radius: 16px;
                box-shadow: 4px 6px 0 #4c1026;
            }
            .nav-link:last-child { flex-basis: 100%; }
            input,
            textarea { border-radius: 16px; }
            table { min-width: 620px; }
            .table-responsive-wrapper { overflow-x: auto; }
        }
    </style>
</head>
<body>
<div class="container" id="mainContent">
    
    <div class="nav-links">
        <a href="index.php" class="nav-link">🏠 Dashboard</a>
        <a href="form.php" class="nav-link active">📝 Form Klasik</a>
        <a href="form_interactive.php" class="nav-link">✨ Form Interaktif</a>
        <a href="form_psikologi.php" class="nav-link">🧠 Tes Psikologi</a>
        <a href="#tabel-riwayat" class="nav-link">🔎 Detail</a>
    </div>

    <!-- <a href="index.php" class="dashboard-link">⬅️ Kembali ke Dashboard Kartu Epik</a> -->

    <h1>Formulir Refleksi Mingguan UKMI</h1>
    <p class="subtitle">Yuk ceritakan perjalananmu minggu ini! 🌟</p>

    <?php if (!empty($pesan_sukses)): ?>
        <div class="success-message"><?php echo htmlspecialchars($pesan_sukses); ?></div>
    <?php endif; ?>
    <?php if (!empty($pesan_error)): ?>
        <div class="error-message"><?php echo $pesan_error; ?></div>
    <?php endif; ?>

    <form action="form.php" method="post">
        <h3>📋 Informasi Dasar</h3>
        <label for="nama_mentee">Nama Mentee</label>
        <input type="text" id="nama_mentee" name="nama_mentee" required value="<?php echo htmlspecialchars($input_values['nama_mentee']); ?>">
        
        <label for="nama_mentor">Nama Mentor</label>
        <input type="text" id="nama_mentor" name="nama_mentor" required value="<?php echo htmlspecialchars($input_values['nama_mentor']); ?>">
        
        <div class="privacy-box">
            <input type="checkbox" id="samarkan_nama" name="samarkan_nama" value="1" style="vertical-align: middle;" <?php if($input_values['samarkan_nama']) echo 'checked'; ?>>
            <label for="samarkan_nama" style="display:inline; font-weight:normal;">🔒 Samarkan nama saya di daftar publik</label>
            <div id="kolom_sandi" style="<?php if($input_values['samarkan_nama']) echo 'display:block;'; ?>">
                <label for="sandi_nama">Buat Sandi untuk Melihat Nama Asli:</label>
                <input type="password" id="sandi_nama" name="sandi_nama" placeholder="Wajib diisi jika nama disamarkan">
            </div>
        </div>
        
        <label for="periode_minggu">Periode Refleksi (Minggu ke-) <span style="font-weight:400; color: var(--muted);">(opsional)</span></label>
        <input type="number" id="periode_minggu" name="periode_minggu" min="1" value="<?php echo htmlspecialchars($input_values['periode_minggu']); ?>" placeholder="Kosongkan jika tidak ingat">
        
        <label for="periode_tanggal">Tanggal Periode</label>
        <input type="text" id="periode_tanggal" name="periode_tanggal" placeholder="cth: 12 Agu - 18 Agu 2025" required value="<?php echo htmlspecialchars($input_values['periode_tanggal']); ?>">
        
        <hr>
        <h3>✨ Pencapaian Minggu Ini</h3>
        <label for="pencapaian">Apa pencapaian atau hal baik yang sudah kamu lakukan minggu ini?</label>
        <textarea id="pencapaian" name="pencapaian" required placeholder="Contoh: Menyelesaikan tugas kuliah tepat waktu, aktif di kegiatan UKMI, belajar skill baru..."><?php echo htmlspecialchars($input_values['pencapaian']); ?></textarea>
        
        <label for="kemajuan_tujuan">Sejauh mana progres kamu terhadap tujuan yang ditetapkan sebelumnya?</label>
        <textarea id="kemajuan_tujuan" name="kemajuan_tujuan" required placeholder="Jelaskan perkembangan dari target yang sudah dibuat bersama mentor"><?php echo htmlspecialchars($input_values['kemajuan_tujuan']); ?></textarea>
        
        <hr>
        <h3>🚧 Tantangan & Pembelajaran</h3>
        <label for="tantangan">Apa tantangan atau kesulitan yang kamu hadapi?</label>
        <textarea id="tantangan" name="tantangan" required placeholder="Contoh: Susah membagi waktu, kesulitan memahami materi, kurang percaya diri..."><?php echo htmlspecialchars($input_values['tantangan']); ?></textarea>
        
        <label for="pembelajaran_baru">Apa yang kamu pelajari minggu ini?</label>
        <textarea id="pembelajaran_baru" name="pembelajaran_baru" required placeholder="Skill baru, insight, atau pelajaran hidup yang kamu dapat"><?php echo htmlspecialchars($input_values['pembelajaran_baru']); ?></textarea>
        
        <hr>
        <h3>⚡ Kondisi & Perasaan</h3>
        <label for="skor_energi">Tingkat Semangat & Motivasi (1-10)</label>
        <input type="number" id="skor_energi" name="skor_energi" min="1" max="10" required placeholder="1 = Sangat rendah, 10 = Sangat tinggi" value="<?php echo htmlspecialchars($input_values['skor_energi']); ?>">
        
        <label for="penjelasan_energi">Kenapa kamu kasih skor segitu? Apa yang bikin semangat atau justru menurun?</label>
        <textarea id="penjelasan_energi" name="penjelasan_energi" required placeholder="Ceritakan kondisi dan perasaanmu minggu ini"><?php echo htmlspecialchars($input_values['penjelasan_energi']); ?></textarea>
        
        <label for="momen_berkesan">Ceritakan 1 momen yang paling berkesan minggu ini</label>
        <textarea id="momen_berkesan" name="momen_berkesan" required placeholder="Momen yang bikin kamu bahagia, bangga, atau terkesan"><?php echo htmlspecialchars($input_values['momen_berkesan']); ?></textarea>
        
        <hr>
        <h3>🎯 Rencana Minggu Depan</h3>
        <label for="prioritas_mingguan">Apa fokus utama kamu minggu depan? (1-3 hal)</label>
        <textarea id="prioritas_mingguan" name="prioritas_mingguan" required placeholder="Contoh: Fokus UTS, persiapan event UKMI, menyelesaikan project..."><?php echo htmlspecialchars($input_values['prioritas_mingguan']); ?></textarea>
        
        <label for="tujuan_spesifik">Target spesifik yang ingin dicapai minggu depan</label>
        <textarea id="tujuan_spesifik" name="tujuan_spesifik" required placeholder="Buat target yang jelas dan bisa diukur, misalnya: 'Selesaikan laporan PKM pada Jumat sore'"><?php echo htmlspecialchars($input_values['tujuan_spesifik']); ?></textarea>
        
        <hr>
        <h3>💬 Yang Ingin Dibahas dengan Mentor</h3>
        <label for="topik_diskusi">Apa yang ingin kamu diskusikan di sesi mentoring nanti?</label>
        <textarea id="topik_diskusi" name="topik_diskusi" required placeholder="Pertanyaan, masalah, atau topik yang ingin dibahas bersama mentor"><?php echo htmlspecialchars($input_values['topik_diskusi']); ?></textarea>
        
        <label>Bantuan apa yang kamu butuhkan dari mentor?</label>
        <div class="checkbox-group">
            <input type="checkbox" id="bantuan1" name="bantuan_diharapkan[]" value="Brainstorming ide" <?php if(in_array('Brainstorming ide', $input_values['bantuan_diharapkan'])) echo 'checked'; ?>><label for="bantuan1">Brainstorming ide</label><br>
            <input type="checkbox" id="bantuan2" name="bantuan_diharapkan[]" value="Feedback & saran" <?php if(in_array('Feedback & saran', $input_values['bantuan_diharapkan'])) echo 'checked'; ?>><label for="bantuan2">Feedback & saran</label><br>
            <input type="checkbox" id="bantuan3" name="bantuan_diharapkan[]" value="Solusi masalah" <?php if(in_array('Solusi masalah', $input_values['bantuan_diharapkan'])) echo 'checked'; ?>><label for="bantuan3">Solusi masalah</label><br>
            <input type="checkbox" id="bantuan4" name="bantuan_diharapkan[]" value="Sharing pengalaman" <?php if(in_array('Sharing pengalaman', $input_values['bantuan_diharapkan'])) echo 'checked'; ?>><label for="bantuan4">Sharing pengalaman</label><br>
            <input type="checkbox" id="bantuan5" name="bantuan_diharapkan[]" value="Curhat & motivasi" <?php if(in_array('Curhat & motivasi', $input_values['bantuan_diharapkan'])) echo 'checked'; ?>><label for="bantuan5">Curhat & motivasi</label>
        </div>

        <label for="catatan_lain">Catatan Tambahan (Opsional)</label>
        <textarea id="catatan_lain" name="catatan_lain" placeholder="Ada hal lain yang ingin kamu sampaikan?"><?php echo htmlspecialchars($input_values['catatan_lain']); ?></textarea>
        
        <button type="submit">Submit Refleksi</button>
    </form>
    
    <hr>
    <div class="riwayat" id="tabel-riwayat">
        <h2>Riwayat Refleksi</h2>
        <div class="table-responsive-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Nama Mentee</th><th>Minggu Ke-</th><th>Periode</th><th>Tanggal Submit</th><th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php if ($row['nama_disamarkan']) { echo "<i>Mentee Anonim</i> 🔒"; } else { echo htmlspecialchars($row['nama_mentee']); } ?></td>
                                <td><?php echo !empty($row['periode_minggu']) ? htmlspecialchars($row['periode_minggu']) : '—'; ?></td>
                                <td><?php echo htmlspecialchars($row['periode_tanggal']); ?></td>
                                <td><?php echo date('d M Y H:i', strtotime($row['tanggal_submit'])); ?></td>
                                <td><a href="detail.php?id=<?php echo $row['id']; ?>">Lihat Detail</a></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center;">Belum ada refleksi yang disubmit.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Privacy checkbox toggle
document.getElementById('samarkan_nama').addEventListener('change', function() {
    var kolomSandi = document.getElementById('kolom_sandi');
    var inputSandi = document.getElementById('sandi_nama');
    if (this.checked) {
        kolomSandi.style.display = 'block';
        inputSandi.required = true;
    } else {
        kolomSandi.style.display = 'none';
        inputSandi.required = false;
    }
});
</script>

</body>
</html>