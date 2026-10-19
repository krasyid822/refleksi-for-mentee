<?php
require_once 'env.php';
include 'gemini_helper.php';


if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}
$id = intval($_GET['id']);

// 1. Ambil data refleksi dari DB
$sql_select = "SELECT * FROM refleksi_mingguan WHERE id = ?";
$stmt_select = $conn->prepare($sql_select);
$stmt_select->bind_param("i", $id);
$stmt_select->execute();
$result = $stmt_select->get_result();

if ($result->num_rows > 0) {
    $data_refleksi_db = $result->fetch_assoc();

    // 2. Panggil AI dengan data dari DB
    $hasil_ai = panggilGemini($data_refleksi_db);

    if ($hasil_ai['sukses']) {
        // 3. Jika berhasil, update DB
        $sqlUpdate = "UPDATE refleksi_mingguan SET kalimat_epik = ? WHERE id = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->bind_param("si", $hasil_ai['kalimat'], $id);
        $stmtUpdate->execute();
        $stmtUpdate->close();
        
        // 4. Kembali ke halaman detail dengan notif sukses
        header("Location: detail.php?id=" . $id . "&status=regen_sukses");
        exit();
    } else {
        // Jika gagal, kembali dengan notif error
        header("Location: detail.php?id=" . $id . "&status=regen_gagal&error=" . urlencode($hasil_ai['error']));
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>