<?php
include 'config.php';

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
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f4f7f6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #0056b3; }
        .detail-item { margin-bottom: 20px; }
        .detail-item h3 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 5px; margin-top: 30px; }
        .detail-item p { white-space: pre-wrap; line-height: 1.6; word-wrap: break-word; }
        .back-link { display: inline-block; padding: 10px 15px; background-color: #6c757d; color: white; text-decoration: none; border-radius: 4px; }
        .back-link:hover { background-color: #5a6268; }
        .info-header { background-color: #e9f4ff; padding: 15px; border-radius: 5px; margin-bottom: 20px; word-wrap: break-word; }
        .privacy-prompt { background-color: #fff3cd; border: 1px solid #ffeeba; padding: 15px; border-radius: 5px; margin-top:15px; }
        .password-form { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .password-form input { flex-grow: 1; min-width: 150px; padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
        .password-form button { flex-shrink: 0; padding: 10px 15px; margin-top:0; border-radius: 4px; border:none; background-color: #007bff; color:white; cursor:pointer; }
        .success-message { background-color: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; text-align: center; }
        .error-message { background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px; text-align: center; font-weight: bold; }
        .btn-regen { display: inline-block; padding: 8px 15px; background-color: #28a745; color: white; text-decoration: none; border-radius: 4px; font-size: 0.9em; }
        .btn-regen:hover { background-color: #218838; }
        .btn-download { display: inline-block; padding: 10px 15px; background-color: #17a2b8; color: white; text-decoration: none; border-radius: 4px; }
        .btn-download:hover { background-color: #138496; }
        .footer-actions { margin-top: 40px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .privacy-toggle { font-size: 0.9em; text-align: right; }
        .privacy-toggle a { color: #dc3545; font-weight: bold; }
        .privacy-toggle a.activate { color: #28a745; }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-link" style="background-color:#0056b3; float:right;">Ke Dashboard</a>
    <h1>Detail Refleksi Mingguan</h1>
    
    <?php echo $notif_extra; ?>
    
    <div class="info-header">
        <?php 
        $nama_mentee_tampil = "<i>Nama Disamarkan</i> 🔒";
        $nama_mentor_tampil = "<i>Nama Disamarkan</i> 🔒";
        if (!$refleksi['nama_disamarkan'] || $nama_terbuka) {
            $nama_mentee_tampil = htmlspecialchars($refleksi['nama_mentee']);
            $nama_mentor_tampil = htmlspecialchars($refleksi['nama_mentor']);
        }
        ?>
        <strong>Mentee:</strong> <?php echo $nama_mentee_tampil; ?><br>
        <strong>Mentor:</strong> <?php echo $nama_mentor_tampil; ?><br>
        <strong>Periode:</strong> Minggu ke-<?php echo htmlspecialchars($refleksi['periode_minggu']); ?> (<?php echo htmlspecialchars($refleksi['periode_tanggal']); ?>)<br>
        <strong>Waktu Submit:</strong> <?php echo date('d F Y, H:i', strtotime($refleksi['tanggal_submit'])); ?>
        
        <?php if ($refleksi['nama_disamarkan'] && !$nama_terbuka): ?>
            <div class="privacy-prompt">
                <p style="margin-top:0; margin-bottom:10px;">Nama pada refleksi ini disamarkan. Masukkan sandi untuk melihat nama asli.</p>
                <form action="detail.php?id=<?php echo $id; ?>" method="post" class="password-form">
                    <input type="password" name="sandi_cek" placeholder="Masukkan Sandi" required>
                    <button type="submit">Tampilkan</button>
                </form>
                <?php if (!empty($error_sandi)): ?>
                    <p class="error-message" style="margin-top:10px; margin-bottom:0;"><?php echo $error_sandi; ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="privacy-toggle">
            <?php if ($refleksi['nama_disamarkan']): ?>
                Status Privasi: Aktif. <a href="toggle_privacy.php?id=<?php echo $id; ?>">Nonaktifkan</a>
            <?php else: ?>
                Status Privasi: Tidak Aktif. <a href="toggle_privacy.php?id=<?php echo $id; ?>" class="activate">Aktifkan</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="detail-item">
        <h3>Kalimat Epik (AI) ✨</h3>
        <p style="font-style: italic; font-size: 1.2em; text-align:center; padding: 10px;">
            "<?php echo !empty($refleksi['kalimat_epik']) ? htmlspecialchars($refleksi['kalimat_epik']) : '<i>Belum dibuat. Coba buat dengan menekan tombol di bawah.</i>'; ?>"
        </p>
        <?php if (empty($refleksi['kalimat_epik'])): ?>
            <a href="regenerate_epic.php?id=<?php echo $id; ?>" class="btn-regen">Buat Kalimat Epik (AI)</a>
        <?php endif; ?>
    </div>
    
    <div class="detail-item"><h3>1. Pencapaian & Kemenangan ✨</h3><p><?php echo nl2br(htmlspecialchars($refleksi['pencapaian'])); ?></p></div>
    <div class="detail-item"><h3>2. Kemajuan Terhadap Tujuan</h3><p><?php echo nl2br(htmlspecialchars($refleksi['kemajuan_tujuan'])); ?></p></div>
    <div class="detail-item"><h3>3. Tantangan & Hambatan 🚧</h3><p><?php echo nl2br(htmlspecialchars($refleksi['tantangan'])); ?></p></div>
    <div class="detail-item"><h3>4. Pembelajaran Baru 🧠</h3><p><?php echo nl2br(htmlspecialchars($refleksi['pembelajaran_baru'])); ?></p></div>
    <div class="detail-item"><h3>5. Tingkat Energi & Motivasi ⚡️</h3><p><strong>Skor: <?php echo htmlspecialchars($refleksi['skor_energi']); ?>/10</strong><br><?php echo nl2br(htmlspecialchars($refleksi['penjelasan_energi'])); ?></p></div>
    <div class="detail-item"><h3>6. Momen Paling Berkesan</h3><p><?php echo nl2br(htmlspecialchars($refleksi['momen_berkesan'])); ?></p></div>
    <div class="detail-item"><h3>7. Prioritas Utama Minggu Depan 🎯</h3><p><?php echo nl2br(htmlspecialchars($refleksi['prioritas_mingguan'])); ?></p></div>
    <div class="detail-item"><h3>8. Satu Tujuan Spesifik & Terukur</h3><p><?php echo nl2br(htmlspecialchars($refleksi['tujuan_spesifik'])); ?></p></div>
    <div class="detail-item"><h3>9. Topik Diskusi Utama 💬</h3><p><?php echo nl2br(htmlspecialchars($refleksi['topik_diskusi'])); ?></p></div>
    <div class="detail-item"><h3>10. Bantuan yang Diharapkan</h3><p><?php echo htmlspecialchars($refleksi['bantuan_diharapkan']); ?></p></div>
    <div class="detail-item"><h3>11. Catatan Lain</h3><p><?php echo !empty($refleksi['catatan_lain']) ? nl2br(htmlspecialchars($refleksi['catatan_lain'])) : '<em>Tidak ada catatan tambahan.</em>'; ?></p></div>
    
    <div class="footer-actions">
        <a href="form.php" class="back-link">Kembali ke Daftar Form</a>
        <a href="download.php?id=<?php echo $id; ?>" class="btn-download" target="_blank">Unduh Refleksi (.txt)</a>
    </div>
</div>
</body>
</html>