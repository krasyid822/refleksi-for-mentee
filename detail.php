<?php
require_once 'env.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}
$id = intval($_GET['id']);
$notif_extra = '';

// Logika notifikasi baru untuk status privasi
if(isset($_GET['status'])){
    $status = $_GET['status'];
    if($status == 'regen_sukses'){
        $notif_extra = '<div class="success-message">Kalimat epik berhasil dibuat ulang!</div>';
    } else if($status == 'regen_gagal'){
        $notif_extra = '<div class="error-message">Gagal membuat ulang: '.htmlspecialchars($_GET['error']).'</div>';
    } else if($status == 'privacy_on'){
        $notif_extra = '<div class="success-message">Mode privasi nama berhasil diaktifkan.</div>';
    } else if($status == 'privacy_off'){
        $notif_extra = '<div class="success-message">Mode privasi nama berhasil dinonaktifkan.</div>';
    }
}

$refleksi = null; $error_sandi = ''; $nama_terbuka = false;
$sql_select = "SELECT * FROM refleksi_mingguan WHERE id = ?";
$stmt_select = $conn->prepare($sql_select);
$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$result = $stmt_select->get_result();
if ($result->num_rows > 0) {
    $refleksi = $result->fetch_assoc();
    $mode_form = (strpos($refleksi['pencapaian'], 'Hasil check-in psikologi singkat:') === 0)
        ? 'Tes Psikologi Singkat'
        : ((strpos((string) $refleksi['penjelasan_energi'], 'Check-in:') === 0) ? 'Form Klasik Terpadu' : 'Refleksi Mingguan');
} else {
    header("Location: index.php");
    exit();
}
$stmt_select->close();

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['sandi_cek'])) {
    $sandi_input = $_POST['sandi_cek'];
    if ($sandi_input === $refleksi['sandi_nama']) {
        $nama_terbuka = true; 
    } else {
        $error_sandi = "Sandi yang Anda masukkan salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Refleksi</title>
    <meta name="theme-color" content="#123b2c">
    <link rel="manifest" href="manifest.json">
    <link rel="stylesheet" href="page-transition.css">
    <link rel="icon" type="image/svg+xml" href="icons/icon-192.svg">
    <link rel="apple-touch-icon" href="icons/icon-192.svg">
    <script src="page-transition.js" defer></script>
    <script src="audio-player.js" defer></script>
    <script src="pwa-register.js" defer></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600&family=Space+Grotesk:wght@400;500&display=swap');

        html { scroll-behavior: smooth; }

        :root {
            --ink: #0f2a20;
            --bg-main: #123b2c;
            --card: #f6fff9;
            --accent: #f97316;
            --accent-dark: #123b2c;
            --muted: rgba(15, 42, 32, 0.72);
            --outline: #0f5132;
            --soft-shadow: #08261c;
            --success: #15803d;
            --danger: #b91c1c;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Space Grotesk', 'Poppins', -apple-system, BlinkMacSystemFont, system-ui, sans-serif;
            background: radial-gradient(circle at top left, rgba(245, 158, 11, 0.22), transparent 28%),
                        radial-gradient(circle at top right, rgba(34, 197, 94, 0.14), transparent 24%),
                        var(--bg-main);
            color: #f8fafc;
            min-height: 100vh;
            padding: 32px 18px 72px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: var(--card);
            color: var(--ink);
            border-radius: 32px;
            border: 3px solid var(--outline);
            box-shadow: 20px 24px 0 var(--soft-shadow);
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
            background: #dbf8e5;
            color: var(--ink);
            font-weight: 600;
            text-decoration: none;
            box-shadow: 6px 8px 0 var(--soft-shadow);
            transition: transform 0.15s ease;
        }

        .nav-link:hover { transform: translateY(-2px); }
        .nav-link.active { background: var(--accent); color: #fff7ed; }

        h1 {
            font-family: 'Fredoka', sans-serif;
            text-transform: uppercase;
            color: #f97316;
            font-size: clamp(2rem, 5vw, 3rem);
            letter-spacing: 0.08em;
            margin-bottom: 4px;
        }

        .subtitle {
            color: var(--muted);
            margin-bottom: 28px;
        }

        .section-divider {
            border: none;
            height: 3px;
            background: var(--outline);
            border-radius: 999px;
            opacity: 0.2;
            margin: 36px 0 24px;
        }

        .info-header {
            background: #fffdf8;
            border: 2px solid var(--outline);
            border-radius: 28px;
            padding: 26px;
            box-shadow: 10px 12px 0 #bfe7d5;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-top: 18px;
        }

        .info-grid p {
            margin: 0;
            font-weight: 600;
            color: var(--ink);
        }

        .info-grid span {
            display: block;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            margin-bottom: 4px;
        }

        .privacy-prompt {
            margin-top: 18px;
            border-radius: 22px;
            border: 2px dashed var(--outline);
            background: #ecfdf5;
            padding: 16px 18px;
            box-shadow: 6px 8px 0 #bfe7d5;
        }

        .privacy-text {
            color: #0f5132;
            font-weight: 600;
            margin: 0 0 10px;
        }

        .password-form {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .password-form input {
            flex: 1;
            min-width: 220px;
            border: 2px solid var(--outline);
            border-radius: 20px;
            padding: 12px 16px;
            font-size: 1rem;
            box-shadow: 4px 6px 0 #bfe7d5;
        }

        .privacy-toggle {
            margin-top: 18px;
            font-weight: 600;
            color: var(--muted);
        }

        .privacy-toggle a { color: var(--accent-dark); }

        .detail-item { margin-top: 28px; }

        .detail-item h3 {
            font-family: 'Fredoka', sans-serif;
            font-size: 1.2rem;
            margin-bottom: 10px;
        }

        .detail-card {
            background: #fff;
            border: 2px solid var(--outline);
            border-radius: 24px;
            padding: 18px 22px;
            box-shadow: 8px 10px 0 #ccd5ff;
            line-height: 1.7;
            color: var(--ink);
            white-space: pre-wrap;
        }

        .epic-quote {
            font-size: 1.2rem;
            font-style: italic;
            text-align: center;
            color: var(--accent-dark);
        }

        .success-message,
        .error-message {
            border-radius: 24px;
            border: 3px solid var(--outline);
            padding: 14px 18px;
            font-weight: 600;
            margin-bottom: 20px;
            box-shadow: 8px 10px 0 var(--soft-shadow);
            text-align: center;
        }

        .success-message { background: #bbf7d0; color: #064e3b; }
        .error-message { background: #ffe4e6; color: #7f1d1d; }

        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 14px 24px;
            border-radius: 999px;
            border: 3px solid var(--outline);
            font-weight: 700;
            text-decoration: none;
            box-shadow: 10px 12px 0 var(--soft-shadow);
            background: #fff;
            color: var(--ink);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .btn-primary { background: var(--accent); color: var(--accent-dark); }
        .btn-outline { background: #fff; }
        .btn-success { background: #bbf7d0; color: #14532d; }
        .btn-danger { background: #fee2e2; color: var(--danger); }

        .btn:hover { transform: translateY(-3px); box-shadow: 14px 16px 0 var(--soft-shadow); }
        .btn.disabled { opacity: 0.45; pointer-events: none; border-style: dashed; }

        .footer-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 40px;
        }

        .footer-actions > * { flex: 1; text-align: center; }

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
            .password-form input { min-width: 100%; }
            .footer-actions { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="container">

    <div class="nav-links">
        <a href="index.php" class="nav-link">🏠 Dashboard</a>
        <a href="form.php" class="nav-link">📝 Form Klasik</a>
        <a href="form_bebas.php" class="nav-link">🪶 Form Bebas</a>
        <a href="#" class="nav-link active">🔎 Detail</a>
    </div>

    <!-- <a href="index.php" class="back-link">⬅️ Ke Dashboard</a>  -->
    <h1>Detail Refleksi Mingguan</h1>
    <p class="subtitle">Semua highlight minggu ini dalam satu layar.</p>

    <?php echo $notif_extra; ?>

    <div class="info-header">
        <div class="privacy-toggle" style="margin-bottom:18px;">
            <strong>Model Form:</strong> <?php echo htmlspecialchars($mode_form); ?>
        </div>
        <?php 
        $nama_mentee_tampil = "<i>Nama Disamarkan</i> 🔒";
        $nama_mentor_tampil = "<i>Nama Disamarkan</i> 🔒";
        if (!$refleksi['nama_disamarkan'] || $nama_terbuka) {
            $nama_mentee_tampil = htmlspecialchars($refleksi['nama_mentee']);
            $nama_mentor_tampil = htmlspecialchars($refleksi['nama_mentor']);
        }
        ?>
        <div class="info-grid">
            <p><span>Mentee</span><?php echo $nama_mentee_tampil; ?></p>
            <p><span>Mentor</span><?php echo $nama_mentor_tampil; ?></p>
            <p><span>Periode</span><?php echo !empty($refleksi['periode_minggu']) ? 'Minggu ke-' . htmlspecialchars($refleksi['periode_minggu']) . ' (' . htmlspecialchars($refleksi['periode_tanggal']) . ')' : htmlspecialchars($refleksi['periode_tanggal']); ?></p>
            <p><span>Waktu Submit</span><?php echo date('d F Y, H:i', strtotime($refleksi['tanggal_submit'])); ?></p>
        </div>
        
        <?php if ($refleksi['nama_disamarkan'] && !$nama_terbuka): ?>
            <div class="privacy-prompt">
                <p class="privacy-text">⚠️ Nama pada refleksi ini disamarkan. Masukkan sandi untuk melihat nama asli.</p>
                <form action="detail.php?id=<?php echo $id; ?>" method="post" class="password-form">
                    <input type="password" name="sandi_cek" placeholder="Masukkan Sandi" required>
                    <button type="submit" class="btn btn-primary">🔓 Tampilkan</button>
                </form>
                <?php if (!empty($error_sandi)): ?>
                    <p class="error-message" style="margin-top:12px; margin-bottom:0;"><?php echo $error_sandi; ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="privacy-toggle">
            <?php if ($refleksi['nama_disamarkan']): ?>
                Status Privasi: 🔒 Aktif. <a href="toggle_privacy.php?id=<?php echo $id; ?>">Nonaktifkan</a>
            <?php else: ?>
                Status Privasi: 🔓 Tidak Aktif. <a href="toggle_privacy.php?id=<?php echo $id; ?>" class="activate">Aktifkan</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="detail-item">
        <h3>✨ Kalimat Epik (AI)</h3>
        <div class="detail-card epic-quote">
            "<?php echo !empty($refleksi['kalimat_epik']) ? htmlspecialchars($refleksi['kalimat_epik']) : '<i>Belum dibuat. Coba buat dengan menekan tombol di bawah.</i>'; ?>"
        </div>
        <?php if (empty($refleksi['kalimat_epik'])): ?>
            <a href="regenerate_epic.php?id=<?php echo $id; ?>" class="btn btn-success" style="margin-top:14px; display:inline-block;">🤖 Buat Kalimat Epik (AI)</a>
        <?php endif; ?>
    </div>

    <hr class="section-divider">

    <div class="detail-item"><h3>1. Pencapaian & Kemenangan ✨</h3><div class="detail-card"><?php echo nl2br(htmlspecialchars($refleksi['pencapaian'])); ?></div></div>
    <div class="detail-item"><h3>2. Kemajuan Terhadap Tujuan</h3><div class="detail-card"><?php echo nl2br(htmlspecialchars($refleksi['kemajuan_tujuan'])); ?></div></div>
    <div class="detail-item"><h3>3. Tantangan & Hambatan 🚧</h3><div class="detail-card"><?php echo nl2br(htmlspecialchars($refleksi['tantangan'])); ?></div></div>
    <div class="detail-item"><h3>4. Pembelajaran Baru 🧠</h3><div class="detail-card"><?php echo nl2br(htmlspecialchars($refleksi['pembelajaran_baru'])); ?></div></div>
    <div class="detail-item"><h3>5. Tingkat Energi & Motivasi ⚡️</h3><div class="detail-card"><strong>Skor: <?php echo htmlspecialchars($refleksi['skor_energi']); ?>/10</strong><br><br><?php echo nl2br(htmlspecialchars($refleksi['penjelasan_energi'])); ?></div></div>
    <div class="detail-item"><h3>6. Momen Paling Berkesan</h3><div class="detail-card"><?php echo nl2br(htmlspecialchars($refleksi['momen_berkesan'])); ?></div></div>
    <div class="detail-item"><h3>7. Prioritas Utama Minggu Depan 🎯</h3><div class="detail-card"><?php echo nl2br(htmlspecialchars($refleksi['prioritas_mingguan'])); ?></div></div>
    <div class="detail-item"><h3>8. Satu Tujuan Spesifik & Terukur</h3><div class="detail-card"><?php echo nl2br(htmlspecialchars($refleksi['tujuan_spesifik'])); ?></div></div>
    <div class="detail-item"><h3>9. Topik Diskusi Utama 💬</h3><div class="detail-card"><?php echo nl2br(htmlspecialchars($refleksi['topik_diskusi'])); ?></div></div>
    <div class="detail-item"><h3>10. Bantuan yang Diharapkan</h3><div class="detail-card"><?php echo htmlspecialchars($refleksi['bantuan_diharapkan']); ?></div></div>
    <div class="detail-item"><h3>11. Catatan Lain</h3><div class="detail-card"><?php echo !empty($refleksi['catatan_lain']) ? nl2br(htmlspecialchars($refleksi['catatan_lain'])) : '<em>Tidak ada catatan tambahan.</em>'; ?></div></div>

    <div class="footer-actions">
        <a href="download.php?id=<?php echo $id; ?>" class="btn btn-outline" target="_blank">📥 Unduh Refleksi (.txt)</a>
        <?php 
        $tanggal_submit = strtotime($refleksi['tanggal_submit']);
        $sekarang = time();
        $selisih_jam = ($sekarang - $tanggal_submit) / 3600;
        if ($selisih_jam <= 24): 
        ?>
            <a href="delete.php?id=<?php echo $id; ?>" class="btn btn-danger">🗑️ Hapus Refleksi</a>
        <?php else: ?>
            <span class="btn btn-danger disabled" title="Refleksi hanya bisa dihapus dalam 24 jam pertama">🗑️ Hapus (Expired)</span>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
