<?php
include 'config.php';

// Ambil semua data refleksi, terutama kalimat epik
$sql = "SELECT id, nama_mentee, nama_disamarkan, periode_minggu, kalimat_epik FROM refleksi_mingguan WHERE kalimat_epik IS NOT NULL AND kalimat_epik != '' ORDER BY tanggal_submit DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refleksi Mingguan</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Merriweather:wght@700&family=Poppins:wght@400;500&display=swap');
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f2f5;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        .header h1 {
            font-family: 'Merriweather', serif;
            font-size: 2.5em;
            color: #1e3a5f;
            margin-bottom: 5px;
        }
        .header p {
            font-size: 1.1em;
            color: #555;
        }
        .header .btn-submit {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 25px;
            background: linear-gradient(90deg, #007bff, #0056b3);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 500;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .header .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.4);
        }
        .card-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .epic-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.08);
            padding: 25px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 180px;
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 5px solid #007bff;
        }
        .epic-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.1);
        }
        .epic-card .quote {
            font-family: 'Merriweather', serif;
            font-size: 1.25em;
            font-weight: 700;
            color: #2c3e50;
            text-align: center;
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .epic-card .quote::before {
            content: '“';
            font-size: 2.5em;
            color: #007bff;
            opacity: 0.5;
            margin-right: 10px;
        }
        .epic-card .quote::after {
            content: '”';
            font-size: 2.5em;
            color: #007bff;
            opacity: 0.5;
            margin-left: 10px;
        }
        .epic-card .author {
            text-align: right;
            margin-top: 20px;
            font-size: 0.95em;
            color: #7f8c8d;
        }
        .epic-card .author a {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }
        .no-data {
            text-align: center;
            grid-column: 1 / -1;
            padding: 40px;
            background-color: #fff;
            border-radius: 10px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Refleksi Mingguan</h1>
        <p>Intisari perjalanan para mentee yang dianalisis oleh AI.</p>
        <a href="form.php" class="btn-submit">✍️ Isi Refleksi Baru</a>
    </div>

    <div class="card-container">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="epic-card">
                    <p class="quote"><?php echo htmlspecialchars($row['kalimat_epik']); ?></p>
                    <p class="author">
                        — 
                        <?php 
                        if ($row['nama_disamarkan']) {
                            echo "<i>Mentee Anonim</i> 🔒";
                        } else {
                            echo htmlspecialchars($row['nama_mentee']);
                        }
                        ?>
                        (Minggu ke-<?php echo htmlspecialchars($row['periode_minggu']); ?>)
                        | <a href="detail.php?id=<?php echo $row['id']; ?>">Lihat Detail</a>
                    </p>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-data">
                <h2>Belum ada Kartu Epik yang bisa ditampilkan.</h2>
                <p>Silakan isi refleksi baru terlebih dahulu untuk membuatnya.</p>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>