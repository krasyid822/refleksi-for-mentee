<?php
include 'config.php';

// Validasi ID dari URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}
$id = intval($_GET['id']);

// Ambil status privasi dan sandi saat ini dari database
try {
    $sql_select = "SELECT nama_disamarkan, sandi_nama FROM refleksi_mingguan WHERE id = ?";
    $stmt_select = $conn->prepare($sql_select);
    $stmt_select->bind_param("i", $id);
    $stmt_select->execute();
    $result = $stmt_select->get_result();
    if ($result->num_rows === 0) throw new Exception("Data refleksi tidak ditemukan.");
    $row = $result->fetch_assoc();
    $stmt_select->close();
} catch (Exception $e) {
    die("Terjadi error saat mengambil data: " . $e->getMessage());
}

$status_sekarang = $row['nama_disamarkan'];

// ==================================================================
// KASUS 1: Pengguna ingin MENGAKTIFKAN privasi (status saat ini 0)
// ==================================================================
if ($status_sekarang == 0) {
    // Jika form untuk membuat sandi baru sudah di-submit
    if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST['sandi_baru'])) {
        $conn->begin_transaction();
        try {
            $sandi_baru = $_POST['sandi_baru'];
            $sqlUpdate = "UPDATE refleksi_mingguan SET nama_disamarkan = 1, sandi_nama = ? WHERE id = ?";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->bind_param("si", $sandi_baru, $id);
            $stmtUpdate->execute();
            $stmtUpdate->close();
            $conn->commit();
            header("Location: detail.php?id=" . $id . "&status=privacy_on");
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            die("Gagal mengaktifkan privasi: " . $e->getMessage());
        }
    } else {
        // Jika belum, tampilkan form untuk MEMBUAT sandi baru
        ?>
        <!DOCTYPE html>
        <html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Buat Sandi Privasi</title><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;background-color:#f0f2f5;margin:0}.form-container{background:white;padding:30px;border-radius:8px;box-shadow:0 4px 15px rgba(0,0,0,.1);width:90%;max-width:400px}h2{color:#0056b3;text-align:center}p{text-align:center;color:#555}label{font-weight:700;display:block;margin-top:20px;margin-bottom:5px}input[type=password]{width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box}button{width:100%;padding:12px;margin-top:20px;border:none;border-radius:5px;background-color:#28a745;color:#fff;font-size:16px;cursor:pointer}button:hover{background-color:#218838}a{display:block;text-align:center;margin-top:15px;color:#6c757d;text-decoration:none}</style></head>
        <body>
            <div class="form-container">
                <h2>Aktifkan Mode Privasi</h2><p>Untuk mengamankan nama, Anda harus membuat sebuah sandi.</p>
                <form action="toggle_privacy.php?id=<?php echo $id; ?>" method="post">
                    <label for="sandi_baru">Buat Sandi Baru:</label>
                    <input type="password" id="sandi_baru" name="sandi_baru" required>
                    <button type="submit">Aktifkan & Simpan Sandi</button>
                </form>
                <a href="detail.php?id=<?php echo $id; ?>">Batal</a>
            </div>
        </body></html>
        <?php
        exit();
    }
}

// =====================================================================
// KASUS 2: Pengguna ingin MENONAKTIFKAN privasi (status saat ini 1)
// =====================================================================
else if ($status_sekarang == 1) {
    $error_konfirmasi = '';
    // Jika form untuk konfirmasi sandi lama sudah di-submit
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['sandi_konfirmasi'])) {
        $sandi_konfirmasi = $_POST['sandi_konfirmasi'];
        
        // Cek apakah sandi yang dimasukkan sama dengan yang di database
        if ($sandi_konfirmasi === $row['sandi_nama']) {
            $conn->begin_transaction();
            try {
                // DIPERBAIKI: Query sekarang tidak menggunakan parameter, jadi lebih aman
                $sqlUpdate = "UPDATE refleksi_mingguan SET nama_disamarkan = 0, sandi_nama = NULL WHERE id = ?";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                // DIPERBAIKI: Menambahkan bind_param yang hilang untuk ID
                $stmtUpdate->bind_param("i", $id);
                $stmtUpdate->execute();
                $stmtUpdate->close();
                // DIPERBAIKI: Menambahkan commit untuk menyimpan perubahan
                $conn->commit();
                header("Location: detail.php?id=" . $id . "&status=privacy_off");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                die("Gagal menonaktifkan privasi: " . $e->getMessage());
            }
        } else {
            $error_konfirmasi = "Sandi konfirmasi salah. Silakan coba lagi.";
        }
    }
    // Jika belum submit, atau jika sandi salah, tampilkan form untuk KONFIRMASI sandi lama
    ?>
    <!DOCTYPE html>
    <html lang="id"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Konfirmasi Penonaktifan Privasi</title><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;background-color:#f0f2f5;margin:0}.form-container{background:white;padding:30px;border-radius:8px;box-shadow:0 4px 15px rgba(0,0,0,.1);width:90%;max-width:400px}h2{color:#c82333;text-align:center}p{text-align:center;color:#555}label{font-weight:700;display:block;margin-top:20px;margin-bottom:5px}input[type=password]{width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box}button{width:100%;padding:12px;margin-top:20px;border:none;border-radius:5px;background-color:#c82333;color:#fff;font-size:16px;cursor:pointer}button:hover{background-color:#bd2130}a{display:block;text-align:center;margin-top:15px;color:#6c757d;text-decoration:none}.error-message{color:red;text-align:center;font-weight:700;margin-top:10px}</style></head>
    <body>
        <div class="form-container">
            <h2>Nonaktifkan Mode Privasi</h2><p>Untuk melanjutkan, masukkan sandi yang ada sebagai konfirmasi.</p>
            <form action="toggle_privacy.php?id=<?php echo $id; ?>" method="post">
                <label for="sandi_konfirmasi">Masukkan Sandi Saat Ini:</label>
                <input type="password" id="sandi_konfirmasi" name="sandi_konfirmasi" required>
                <?php if(!empty($error_konfirmasi)): ?>
                    <p class="error-message"><?php echo $error_konfirmasi; ?></p>
                <?php endif; ?>
                <button type="submit">Konfirmasi & Nonaktifkan</button>
            </form>
            <a href="detail.php?id=<?php echo $id; ?>">Batal</a>
        </div>
    </body></html>
    <?php
    exit();
}

$conn->close();
?>