<?php
include 'config.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID tidak valid.");
}
$id = intval($_GET['id']);

$sql = "SELECT * FROM refleksi_mingguan WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();

    // ==================================================================
    // PERUBAHAN LOGIKA PRIVASI DI SINI
    // ==================================================================
    
    // 1. Tentukan nama yang akan ditampilkan berdasarkan status privasi
    $nama_mentee_tampil = '';
    $nama_mentor_tampil = '';

    if ($row['nama_disamarkan']) {
        $nama_mentee_tampil = "Nama Disamarkan (Privasi Aktif)";
        $nama_mentor_tampil = "Nama Disamarkan (Privasi Aktif)";
    } else {
        $nama_mentee_tampil = $row['nama_mentee'];
        $nama_mentor_tampil = $row['nama_mentor'];
    }

    // 2. Siapkan nama file dengan mempertimbangkan privasi
    $filename_mentee_part = $row['nama_disamarkan'] ? "Mentee-Anonim" : $row['nama_mentee'];
    $filename = "refleksi-" . preg_replace('/[^A-Za-z0-9\-]/', '-', $filename_mentee_part) . "-minggu-" . $row['periode_minggu'] . ".txt";

    // ==================================================================

    // Siapkan konten teks menggunakan nama yang sudah diproses
    $content = "==================================================\n";
    $content .= "      HASIL REFLEKSI MINGGUAN\n";
    $content .= "==================================================\n\n";
    $content .= "Nama Mentee         : " . $nama_mentee_tampil . "\n";
    $content .= "Nama Mentor         : " . $nama_mentor_tampil . "\n";
    $content .= "Periode             : Minggu ke-" . $row['periode_minggu'] . " (" . $row['periode_tanggal'] . ")\n";
    $content .= "Tanggal Submit      : " . date('d F Y, H:i', strtotime($row['tanggal_submit'])) . "\n\n";
    
    $content .= "--------------------------------------------------\n";
    $content .= "Kalimat Epik (AI)\n";
    $content .= "--------------------------------------------------\n";
    $content .= '"' . ($row['kalimat_epik'] ?: 'Belum dibuat.') . '"' . "\n\n";

    $content .= "--------------------------------------------------\n";
    $content .= "1. Pencapaian & Kemenangan\n";
    $content .= "--------------------------------------------------\n";
    $content .= $row['pencapaian'] . "\n\n";

    $content .= "--------------------------------------------------\n";
    $content .= "2. Kemajuan Terhadap Tujuan\n";
    $content .= "--------------------------------------------------\n";
    $content .= $row['kemajuan_tujuan'] . "\n\n";

    $content .= "--------------------------------------------------\n";
    $content .= "3. Tantangan & Hambatan\n";
    $content .= "--------------------------------------------------\n";
    $content .= $row['tantangan'] . "\n\n";
    
    $content .= "--------------------------------------------------\n";
    $content .= "4. Pembelajaran Baru\n";
    $content .= "--------------------------------------------------\n";
    $content .= $row['pembelajaran_baru'] . "\n\n";

    $content .= "--------------------------------------------------\n";
    $content .= "5. Tingkat Energi & Motivasi\n";
    $content .= "--------------------------------------------------\n";
    $content .= "Skor: " . $row['skor_energi'] . "/10\n";
    $content .= "Penjelasan: " . $row['penjelasan_energi'] . "\n\n";
    
    $content .= "--------------------------------------------------\n";
    $content .= "6. Momen Paling Berkesan\n";
    $content .= "--------------------------------------------------\n";
    $content .= $row['momen_berkesan'] . "\n\n";
    
    $content .= "--------------------------------------------------\n";
    $content .= "7. Prioritas Utama Minggu Depan\n";
    $content .= "--------------------------------------------------\n";
    $content .= $row['prioritas_mingguan'] . "\n\n";
    
    $content .= "--------------------------------------------------\n";
    $content .= "8. Satu Tujuan Spesifik & Terukur\n";
    $content .= "--------------------------------------------------\n";
    $content .= $row['tujuan_spesifik'] . "\n\n";

    $content .= "--------------------------------------------------\n";
    $content .= "9. Topik Diskusi Utama untuk Sesi Mentoring\n";
    $content .= "--------------------------------------------------\n";
    $content .= $row['topik_diskusi'] . "\n\n";

    $content .= "--------------------------------------------------\n";
    $content .= "10. Bantuan yang Diharapkan\n";
    $content .= "--------------------------------------------------\n";
    $content .= $row['bantuan_diharapkan'] . "\n\n";

    $content .= "--------------------------------------------------\n";
    $content .= "11. Catatan Lain\n";
    $content .= "--------------------------------------------------\n";
    $content .= (!empty($row['catatan_lain']) ? $row['catatan_lain'] : 'Tidak ada.') . "\n\n";

    // Header untuk memaksa browser mengunduh file
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Tampilkan konten
    echo $content;
    exit();

} else {
    die("Data refleksi tidak ditemukan.");
}

$stmt->close();
$conn->close();
?>