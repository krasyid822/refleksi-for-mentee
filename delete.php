<?php
require_once 'env.php';

// Cek apakah ada ID yang dikirim
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID refleksi tidak valid.");
}

$id = (int)$_GET['id'];

// Ambil data refleksi untuk verifikasi
$sql = "SELECT id, nama_mentee, tanggal_submit FROM refleksi_mingguan WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Refleksi tidak ditemukan.");
}

$refleksi = $result->fetch_assoc();
$stmt->close();

// Hitung selisih waktu
$tanggal_submit = strtotime($refleksi['tanggal_submit']);
$sekarang = time();
$selisih_detik = $sekarang - $tanggal_submit;
$selisih_jam = $selisih_detik / 3600;

// Cek apakah sudah lebih dari 24 jam
if ($selisih_jam > 24) {
    $pesan_error = "❌ Refleksi ini sudah berumur lebih dari 24 jam dan tidak bisa dihapus. (Usia: " . round($selisih_jam, 1) . " jam)";
} else {
    // Proses delete
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['confirm_delete'])) {
        $delete_sql = "DELETE FROM refleksi_mingguan WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $id);
        
        if ($delete_stmt->execute()) {
            $delete_stmt->close();
            $conn->close();
            header("Location: index.php?deleted=success");
            exit();
        } else {
            $pesan_error = "❌ Gagal menghapus refleksi: " . $delete_stmt->error;
            $delete_stmt->close();
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hapus Refleksi</title>
    <script src="audio-player.js" defer></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600&family=Space+Grotesk:wght@400;500&display=swap');

        :root {
            --ink: #0f172a;
            --bg-main: #1d2f8a;
            --card: #eff2ff;
            --accent: #ffd166;
            --accent-dark: #0b1140;
            --danger: #dc2626;
            --muted: rgba(15, 23, 42, 0.7);
            --outline: #0b1140;
            --soft-shadow: #0b1445;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Space Grotesk', 'Poppins', -apple-system, BlinkMacSystemFont, system-ui, sans-serif;
            background: var(--bg-main);
            color: var(--ink);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
        }

        .container {
            width: 100%;
            max-width: 560px;
            background: var(--card);
            border-radius: 32px;
            border: 3px solid var(--outline);
            padding: 40px;
            box-shadow: 20px 24px 0 var(--soft-shadow);
            text-align: center;
        }

        h1 {
            font-family: 'Fredoka', sans-serif;
            font-size: 2rem;
            color: var(--accent);
            margin-bottom: 16px;
        }

        .warning-box,
        .info-box,
        .error-message {
            border: 2px solid var(--outline);
            border-radius: 24px;
            padding: 20px 22px;
            text-align: left;
            box-shadow: 8px 10px 0 #ccd5ff;
            margin: 18px 0;
        }

        .warning-box {
            background: #fff6e0;
        }

        .warning-box strong { display: block; margin-bottom: 6px; color: var(--accent-dark); }

        .info-box {
            background: #fff;
            color: var(--ink);
        }

        .info-box strong { color: var(--accent-dark); }

        .time-info { font-size: 0.9rem; color: var(--muted); margin-top: 6px; }
        .info-success { color: #15803d; font-weight: 600; margin-top: 12px; }

        .error-message {
            background: #ffe4e6;
            color: #7f1d1d;
            text-align: center;
        }

        .helper-text { color: var(--muted); margin: 18px 0; }

        .btn-row {
            display: flex;
            gap: 14px;
            margin-top: 24px;
            flex-wrap: wrap;
        }

        .btn {
            flex: 1;
            border: 3px solid var(--outline);
            border-radius: 999px;
            padding: 14px 20px;
            font-weight: 700;
            text-decoration: none;
            font-family: 'Space Grotesk', sans-serif;
            color: var(--ink);
            background: #fff;
            box-shadow: 10px 12px 0 var(--soft-shadow);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            display: inline-flex;
            justify-content: center;
            gap: 8px;
        }

        .btn:hover { transform: translateY(-3px); box-shadow: 14px 16px 0 var(--soft-shadow); }

        .btn-outline { background: #fff; }
        .btn-danger { background: #fee2e2; color: var(--danger); }
        .btn-primary { background: var(--accent); color: var(--ink); }

        .btn-full { width: 100%; margin-top: 20px; }

        form { margin: 0; }

        @media (max-width: 540px) {
            .container { padding: 30px 22px; box-shadow: 14px 18px 0 var(--soft-shadow); }
            .btn-row { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="container">
    <?php if (isset($pesan_error)): ?>
        <h1>⏰ Tidak Dapat Dihapus</h1>
        <div class="error-message"><?php echo $pesan_error; ?></div>
        <p class="helper-text">Refleksi hanya bisa dihapus dalam 24 jam pertama agar data mentoring tetap rapi.</p>
        <a href="detail.php?id=<?php echo $id; ?>" class="btn btn-primary btn-full">⬅️ Kembali ke Detail</a>
    <?php else: ?>
        <h1>🗑️ Konfirmasi Hapus</h1>
        
        <div class="warning-box">
            <strong>⚠️ Peringatan</strong>
            Apakah Anda yakin ingin menghapus refleksi ini? Tindakan ini tidak dapat dibatalkan!
        </div>
        
        <div class="info-box">
            <p><strong>Nama Mentee:</strong> <?php echo htmlspecialchars($refleksi['nama_mentee']); ?></p>
            <p><strong>Tanggal Submit:</strong> <?php echo date('d M Y H:i', strtotime($refleksi['tanggal_submit'])); ?></p>
            <p class="time-info">⏱️ Disubmit <?php echo round($selisih_jam, 1); ?> jam yang lalu</p>
            <p class="info-success">✅ Masih dalam periode 24 jam - dapat dihapus</p>
        </div>
        
        <form method="post" action="delete.php?id=<?php echo $id; ?>">
            <div class="btn-row">
                <a href="detail.php?id=<?php echo $id; ?>" class="btn btn-outline">❌ Batal</a>
                <button type="submit" name="confirm_delete" class="btn btn-danger" onclick="return confirm('Yakin ingin menghapus refleksi ini?');">🗑️ Ya, Hapus</button>
            </div>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
