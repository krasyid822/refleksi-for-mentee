<?php
include 'config.php';
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
        $pesan_error = "PROSES SUBMIT DIBATALKAN. Kegagalan AI: " . htmlspecialchars($hasil_ai['error']) . " Data Anda di bawah ini aman dan tidak hilang, silakan coba submit lagi.";
    } else {
        // 4. Jika AI berhasil, kumpulkan semua data lain dan lakukan INSERT
        $kalimat_epik = $hasil_ai['kalimat'];
        
        $nama_mentee = $_POST['nama_mentee']; $nama_mentor = $_POST['nama_mentor'];
        $nama_disamarkan = isset($_POST['samarkan_nama']) ? 1 : 0;
        $sandi_nama = ($nama_disamarkan == 1 && !empty($_POST['sandi_nama'])) ? $_POST['sandi_nama'] : null;
        if ($nama_disamarkan == 1 && $sandi_nama === null) $nama_disamarkan = 0;
        
        $periode_minggu = (int)$_POST['periode_minggu'];
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
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f4f7f6; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .dashboard-link { display: block; text-align: center; margin-bottom: 20px; padding: 10px; background-color: #17a2b8; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .dashboard-link:hover { background-color: #138496; }
        h1, h2, h3 { color: #0056b3; } h1 { text-align: center; font-size: 1.8em; }
        hr { border: 0; height: 1px; background: #e0e0e0; margin: 30px 0; }
        label { font-weight: bold; display: block; margin-top: 20px; margin-bottom: 5px; }
        input[type="text"], input[type="number"], input[type="password"], textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 1em; }
        textarea { resize: vertical; min-height: 100px; }
        .checkbox-group label { font-weight: normal; display: inline-block; margin-right: 15px; }
        button { background-color: #007bff; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; display: block; width: 100%; margin-top: 20px; }
        button:hover { background-color: #0056b3; }
        .success-message { background-color: #d4edda; color: #155724; padding: 15px; border-radius: 4px; margin-bottom: 20px; text-align: center; }
        .error-message { background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px; text-align: left; }
        .privacy-box { background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 5px; margin-top: 10px; }
        #kolom_sandi { display: none; margin-top:10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; white-space: nowrap; }
        th { background-color: #007bff; color: white; } tr:nth-child(even) { background-color: #f2f2f2; }
        a { color: #007bff; text-decoration: none; } a:hover { text-decoration: underline; }
        .table-responsive-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; border: 1px solid #ddd; border-radius: 4px; }
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="dashboard-link">⬅️ Kembali ke Dashboard Kartu Epik</a>
    <h1>Formulir Refleksi Mingguan</h1>
    <p style="text-align:center;">Isilah dengan jujur untuk sesi mentoring yang lebih produktif.</p>

    <?php if (!empty($pesan_sukses)): ?>
        <div class="success-message"><?php echo htmlspecialchars($pesan_sukses); ?></div>
    <?php endif; ?>
    <?php if (!empty($pesan_error)): ?>
        <div class="error-message"><?php echo $pesan_error; ?></div>
    <?php endif; ?>

    <form action="form.php" method="post">
        <h3>Informasi Dasar</h3>
        <label for="nama_mentee">Nama Mentee</label>
        <input type="text" id="nama_mentee" name="nama_mentee" required value="<?php echo htmlspecialchars($input_values['nama_mentee']); ?>">
        
        <label for="nama_mentor">Nama Mentor</label>
        <input type="text" id="nama_mentor" name="nama_mentor" required value="<?php echo htmlspecialchars($input_values['nama_mentor']); ?>">
        
        <div class="privacy-box">
            <input type="checkbox" id="samarkan_nama" name="samarkan_nama" value="1" style="vertical-align: middle;" <?php if($input_values['samarkan_nama']) echo 'checked'; ?>>
            <label for="samarkan_nama" style="display:inline; font-weight:normal;">Samarkan nama saya di daftar publik.</label>
            <div id="kolom_sandi" style="<?php if($input_values['samarkan_nama']) echo 'display:block;'; ?>">
                <label for="sandi_nama">Buat Sandi untuk Melihat Nama Asli:</label>
                <input type="password" id="sandi_nama" name="sandi_nama" placeholder="Wajib diisi jika nama disamarkan">
            </div>
        </div>
        
        <label for="periode_minggu">Periode Refleksi (Minggu ke-)</label>
        <input type="number" id="periode_minggu" name="periode_minggu" required value="<?php echo htmlspecialchars($input_values['periode_minggu']); ?>">
        
        <label for="periode_tanggal">Tanggal Periode</label>
        <input type="text" id="periode_tanggal" name="periode_tanggal" placeholder="cth: 12 Agu - 18 Agu 2025" required value="<?php echo htmlspecialchars($input_values['periode_tanggal']); ?>">
        
        <hr>
        <h3>Bagian 1: Refleksi Minggu Lalu</h3>
        <label for="pencapaian">1. Pencapaian & Kemenangan ✨</label>
        <textarea id="pencapaian" name="pencapaian" required placeholder="Contoh: Berhasil menyelesaikan modul A, mendapat pujian dari manajer, atau memperbaiki bug yang sulit."><?php echo htmlspecialchars($input_values['pencapaian']); ?></textarea>
        
        <label for="kemajuan_tujuan">2. Kemajuan Terhadap Tujuan</label>
        <textarea id="kemajuan_tujuan" name="kemajuan_tujuan" required placeholder="Jelaskan progres Anda dari tujuan yang ditetapkan minggu lalu. Apa yang sudah tercapai?"><?php echo htmlspecialchars($input_values['kemajuan_tujuan']); ?></textarea>
        
        <label for="tantangan">3. Tantangan & Hambatan 🚧</label>
        <textarea id="tantangan" name="tantangan" required placeholder="Contoh: Kesulitan memahami konsep X, merasa kurang percaya diri saat presentasi, atau ada konflik dengan rekan kerja."><?php echo htmlspecialchars($input_values['tantangan']); ?></textarea>
        
        <hr>
        <h3>Bagian 2: Pembelajaran & Perasaan</h3>
        <label for="pembelajaran_baru">4. Pembelajaran Baru 🧠</label>
        <textarea id="pembelajaran_baru" name="pembelajaran_baru" required placeholder="Skill teknis, soft skill, atau wawasan baru apa yang Anda dapatkan minggu ini?"><?php echo htmlspecialchars($input_values['pembelajaran_baru']); ?></textarea>
        
        <label for="skor_energi">5. Tingkat Energi & Motivasi ⚡️</label>
        <input type="number" id="skor_energi" name="skor_energi" min="1" max="10" required placeholder="Beri skor 1 (sangat rendah) hingga 10 (sangat tinggi)" value="<?php echo htmlspecialchars($input_values['skor_energi']); ?>">
        
        <label for="penjelasan_energi">Penjelasan Skor Energi & Motivasi</label>
        <textarea id="penjelasan_energi" name="penjelasan_energi" required placeholder="Apa yang membuat skor Anda seperti itu? Apa yang paling menguras atau memberi energi?"><?php echo htmlspecialchars($input_values['penjelasan_energi']); ?></textarea>
        
        <label for="momen_berkesan">6. Momen Paling Berkesan</label>
        <textarea id="momen_berkesan" name="momen_berkesan" required placeholder="Ceritakan satu momen yang membuat Anda merasa paling bangga, bersemangat, atau tercerahkan."><?php echo htmlspecialchars($input_values['momen_berkesan']); ?></textarea>
        
        <hr>
        <h3>Bagian 3: Rencana Minggu Depan</h3>
        <label for="prioritas_mingguan">7. Prioritas Utama Minggu Depan 🎯</label>
        <textarea id="prioritas_mingguan" name="prioritas_mingguan" required placeholder="Sebutkan 1-3 hal terpenting yang harus Anda fokuskan atau selesaikan minggu depan."><?php echo htmlspecialchars($input_values['prioritas_mingguan']); ?></textarea>
        
        <label for="tujuan_spesifik">8. Satu Tujuan Spesifik & Terukur</label>
        <textarea id="tujuan_spesifik" name="tujuan_spesifik" required placeholder="Contoh: 'Menyelesaikan draf pertama laporan proyek Z pada hari Kamis pukul 5 sore'."><?php echo htmlspecialchars($input_values['tujuan_spesifik']); ?></textarea>
        
        <hr>
        <h3>Bagian 4: Dukungan yang Dibutuhkan</h3>
        <label for="topik_diskusi">9. Topik Diskusi Utama untuk Sesi Mentoring 💬</label>
        <textarea id="topik_diskusi" name="topik_diskusi" required placeholder="Apa pertanyaan atau masalah spesifik yang ingin Anda bahas bersama mentor?"><?php echo htmlspecialchars($input_values['topik_diskusi']); ?></textarea>
        
        <label>10. Bantuan yang Diharapkan</label>
        <div class="checkbox-group">
            <input type="checkbox" id="bantuan1" name="bantuan_diharapkan[]" value="Brainstorming ide" <?php if(in_array('Brainstorming ide', $input_values['bantuan_diharapkan'])) echo 'checked'; ?>><label for="bantuan1">Brainstorming ide</label><br>
            <input type="checkbox" id="bantuan2" name="bantuan_diharapkan[]" value="Feedback hasil kerja" <?php if(in_array('Feedback hasil kerja', $input_values['bantuan_diharapkan'])) echo 'checked'; ?>><label for="bantuan2">Feedback hasil kerja</label><br>
            <input type="checkbox" id="bantuan3" name="bantuan_diharapkan[]" value="Saran mengatasi tantangan" <?php if(in_array('Saran mengatasi tantangan', $input_values['bantuan_diharapkan'])) echo 'checked'; ?>><label for="bantuan3">Saran mengatasi tantangan</label><br>
            <input type="checkbox" id="bantuan4" name="bantuan_diharapkan[]" value="Berbagi pengalaman/koneksi" <?php if(in_array('Berbagi pengalaman/koneksi', $input_values['bantuan_diharapkan'])) echo 'checked'; ?>><label for="bantuan4">Berbagi pengalaman/koneksi</label><br>
            <input type="checkbox" id="bantuan5" name="bantuan_diharapkan[]" value="Menjadi pendengar" <?php if(in_array('Menjadi pendengar', $input_values['bantuan_diharapkan'])) echo 'checked'; ?>><label for="bantuan5">Menjadi pendengar</label>
        </div>

        <label for="catatan_lain">11. Catatan Lain</label>
        <textarea id="catatan_lain" name="catatan_lain" placeholder="Hal lain yang ingin Anda sampaikan?"><?php echo htmlspecialchars($input_values['catatan_lain']); ?></textarea>
        
        <button type="submit">Submit Refleksi</button>
    </form>
    
    <hr>
    <div class="riwayat">
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
                                <td><?php echo htmlspecialchars($row['periode_minggu']); ?></td>
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