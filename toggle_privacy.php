<?php
require_once 'env.php';

function renderPrivacyPage($mode, $id, $errorMessage = '') {
    $isDeactivate = ($mode === 'deactivate');
    $title = $isDeactivate ? 'Nonaktifkan Mode Privasi' : 'Aktifkan Mode Privasi';
    $icon = $isDeactivate ? '🔓' : '🔒';
    $description = $isDeactivate
        ? 'Masukkan sandi yang pernah kamu buat untuk menampilkan nama asli refleksi ini.'
        : 'Buat sandi baru agar nama mentee dan mentor tetap tersembunyi di halaman publik.';
    $label = $isDeactivate ? 'Masukkan Sandi:' : 'Buat Sandi Baru:';
    $fieldName = $isDeactivate ? 'sandi_konfirmasi' : 'sandi_baru';
    $buttonText = $isDeactivate ? '⚠️ Konfirmasi & Nonaktifkan' : '🔐 Aktifkan & Simpan Sandi';
    $bodyClass = $isDeactivate ? 'danger-mode' : '';
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?></title>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600&family=Space+Grotesk:wght@400;500&display=swap');

            :root {
                --ink: #0f172a;
                --bg-main: #1d2f8a;
                --card: #eff2ff;
                --accent: #ffd166;
                --danger: #f87171;
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

            body.danger-mode { background: #b91c1c; }

            .form-card {
                width: 100%;
                max-width: 480px;
                background: var(--card);
                border: 3px solid var(--outline);
                border-radius: 32px;
                padding: 36px;
                box-shadow: 18px 22px 0 var(--soft-shadow);
            }

            body.danger-mode .form-card {
                background: #fee2e2;
                box-shadow: 18px 22px 0 #7f1d1d;
            }

            .hero-icon {
                font-size: 3rem;
                text-align: center;
                margin-bottom: 12px;
            }

            h2 {
                font-family: 'Fredoka', sans-serif;
                font-size: 1.8rem;
                text-align: center;
                color: var(--ink);
            }

            body.danger-mode h2 { color: #7f1d1d; }

            p.description {
                text-align: center;
                color: rgba(15, 23, 42, 0.7);
                margin: 10px 0 26px;
                line-height: 1.5;
            }

            body.danger-mode p.description { color: #7f1d1d; }

            label {
                font-weight: 600;
                display: block;
                margin-bottom: 8px;
                color: var(--ink);
            }

            body.danger-mode label { color: #7f1d1d; }

            input[type="password"] {
                width: 100%;
                border: 2px solid var(--outline);
                border-radius: 20px;
                padding: 14px 16px;
                font-size: 1rem;
                font-family: inherit;
                box-shadow: 6px 8px 0 #cbd4ff;
            }

            body.danger-mode input[type="password"] {
                border-color: #7f1d1d;
                box-shadow: 6px 8px 0 #fecaca;
            }

            button {
                width: 100%;
                margin-top: 20px;
                border: 3px solid var(--outline);
                border-radius: 999px;
                padding: 14px 20px;
                font-weight: 700;
                font-size: 1rem;
                cursor: pointer;
                background: var(--accent);
                color: var(--ink);
                box-shadow: 12px 14px 0 var(--soft-shadow);
                transition: transform 0.15s ease, box-shadow 0.15s ease;
            }

            button:hover { transform: translateY(-3px); box-shadow: 16px 18px 0 var(--soft-shadow); }

            body.danger-mode button {
                background: #fecaca;
                border-color: #7f1d1d;
                color: #7f1d1d;
                box-shadow: 12px 14px 0 #7f1d1d;
            }

            .error-message {
                background: #ffe4e6;
                color: #7f1d1d;
                border: 3px solid var(--outline);
                border-radius: 18px;
                padding: 12px 16px;
                font-weight: 600;
                margin-top: 16px;
                text-align: center;
                box-shadow: 8px 10px 0 var(--soft-shadow);
            }

            body.danger-mode .error-message {
                border-color: #7f1d1d;
                box-shadow: 8px 10px 0 #7f1d1d;
            }

            .back-link {
                display: block;
                margin-top: 20px;
                text-align: center;
                font-weight: 600;
                color: var(--ink);
                text-decoration: none;
            }

            body.danger-mode .back-link { color: #7f1d1d; }

            @media (max-width: 560px) {
                .form-card { padding: 28px 22px; border-radius: 24px; }
            }
        </style>
    </head>
    <body class="<?php echo $bodyClass; ?>">
        <div class="form-card">
            <div class="hero-icon"><?php echo $icon; ?></div>
            <h2><?php echo htmlspecialchars($title); ?></h2>
            <p class="description"><?php echo htmlspecialchars($description); ?></p>
            <form action="toggle_privacy.php?id=<?php echo $id; ?>" method="post">
                <label for="field_input"><?php echo htmlspecialchars($label); ?></label>
                <input type="password" id="field_input" name="<?php echo $fieldName; ?>" placeholder="********" required>
                <?php if (!empty($errorMessage)): ?>
                    <div class="error-message">❌ <?php echo htmlspecialchars($errorMessage); ?></div>
                <?php endif; ?>
                <button type="submit"><?php echo htmlspecialchars($buttonText); ?></button>
            </form>
            <a href="detail.php?id=<?php echo $id; ?>" class="back-link">Batal</a>
        </div>
    </body>
    </html>
    <?php
    exit();
}

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
        renderPrivacyPage('activate', $id);
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
                $sqlUpdate = "UPDATE refleksi_mingguan SET nama_disamarkan = 0, sandi_nama = NULL WHERE id = ?";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                $stmtUpdate->bind_param("i", $id);
                $stmtUpdate->execute();
                $stmtUpdate->close();
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
    renderPrivacyPage('deactivate', $id, $error_konfirmasi);
}

$conn->close();
?>
