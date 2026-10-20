<?php
require_once 'env.php';

date_default_timezone_set("Asia/Jakarta");
$zona_waktu = date_default_timezone_get();

$search_query = trim($_GET['q'] ?? '');

// Ambil data refleksi, terutama kalimat epik
if ($search_query !== '') {
    $like_query = '%' . $search_query . '%';
    $sql = "SELECT id, nama_mentee, nama_disamarkan, periode_minggu, kalimat_epik FROM refleksi_mingguan WHERE kalimat_epik IS NOT NULL AND kalimat_epik != '' AND (nama_mentee LIKE ? OR kalimat_epik LIKE ? OR periode_minggu LIKE ?) ORDER BY tanggal_submit DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $like_query, $like_query, $like_query);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $sql = "SELECT id, nama_mentee, nama_disamarkan, periode_minggu, kalimat_epik FROM refleksi_mingguan WHERE kalimat_epik IS NOT NULL AND kalimat_epik != '' ORDER BY tanggal_submit DESC";
    $result = $conn->query($sql);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refleksi Mingguan - Dashboard</title>
    <meta name="theme-color" content="#1d2f8a">
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="icons/icon-192.svg">
    <link rel="apple-touch-icon" href="icons/icon-192.svg">
    <script src="pwa-register.js" defer></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600&family=Space+Grotesk:wght@400;500&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Space Grotesk', 'Poppins', -apple-system, BlinkMacSystemFont, system-ui, sans-serif;
            background: #1d2f8a;
            color: #f4f6fb;
            min-height: 100vh;
            padding: 32px 24px 80px;
        }

        .header {
            text-align: center;
            margin-bottom: 48px;
        }

        .header h1 {
            font-family: 'Fredoka', 'Poppins', sans-serif;
            font-size: clamp(2.4rem, 6vw, 3.4rem);
            color: #ffd166;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 12px;
        }

        .header p {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.86);
        }

        .meta-row {
            max-width: 1300px;
            margin: 0 auto 28px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
        }

        .meta-card,
        .search-card {
            background: rgba(9, 15, 40, 0.72);
            border: 2px solid rgba(255, 255, 255, 0.12);
            border-radius: 24px;
            padding: 18px 20px;
            box-shadow: 0 14px 30px rgba(0, 0, 0, 0.24);
            backdrop-filter: blur(10px);
        }

        .meta-card .label,
        .search-card .label {
            display: block;
            font-size: 0.84rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.58);
            margin-bottom: 8px;
        }

        .clock {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
            padding-top: 4px;
        }

        .clock-face {
            position: relative;
            flex: 0 0 auto;
            width: 132px;
            height: 132px;
            border-radius: 50%;
            background:
                radial-gradient(circle at 50% 45%, rgba(255, 255, 255, 0.10), transparent 62%),
                rgba(9, 15, 40, 0.92);
            border: 2px solid rgba(255, 255, 255, 0.24);
            box-shadow: inset 0 0 26px rgba(0, 0, 0, 0.5), 0 10px 24px rgba(0, 0, 0, 0.28);
        }

        .clock-face::before {
            content: "";
            position: absolute;
            inset: 7px;
            border-radius: 50%;
            background: repeating-conic-gradient(from -0.5deg, rgba(255, 255, 255, 0.55) 0 1.2deg, transparent 1.2deg 30deg);
            -webkit-mask: radial-gradient(circle, transparent 0 79%, #000 80%);
            mask: radial-gradient(circle, transparent 0 79%, #000 80%);
        }

        .clock-hand {
            position: absolute;
            left: 50%;
            bottom: 50%;
            transform-origin: 50% 100%;
            border-radius: 999px;
        }

        .clock-hand.hour {
            width: 6px;
            height: 34px;
            margin-left: -3px;
            background: #ffd166;
        }

        .clock-hand.minute {
            width: 4px;
            height: 48px;
            margin-left: -2px;
            background: #f4f6fb;
        }

        .clock-hand.second {
            width: 2px;
            height: 56px;
            margin-left: -1px;
            background: #ff6b6b;
        }

        .clock-center {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 11px;
            height: 11px;
            margin: -5.5px 0 0 -5.5px;
            border-radius: 50%;
            background: #ffd166;
            box-shadow: 0 0 0 3px rgba(9, 15, 40, 0.95);
        }

        .clock-info {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .clock-digital {
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            font-variant-numeric: tabular-nums;
            color: #ffffff;
        }

        .clock-zone {
            font-size: 0.82rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .search-form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .search-form input {
            flex: 1;
            min-width: 180px;
            border: 2px solid rgba(255, 255, 255, 0.16);
            border-radius: 999px;
            padding: 14px 18px;
            background: rgba(255, 255, 255, 0.06);
            color: #fff;
            font: inherit;
        }

        .search-form input::placeholder { color: rgba(255, 255, 255, 0.55); }

        .search-form button {
            border: 0;
            border-radius: 999px;
            padding: 14px 20px;
            background: #ffd166;
            color: #142044;
            font-weight: 700;
            cursor: pointer;
        }

        .search-form a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 14px 18px;
            border-radius: 999px;
            border: 2px solid rgba(255, 255, 255, 0.16);
            color: #fff;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.06);
        }

        .search-summary {
            max-width: 1300px;
            margin: 0 auto 18px;
            color: rgba(255, 255, 255, 0.82);
            font-size: 0.98rem;
        }

        .btn-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 24px;
            padding: 16px 32px;
            border-radius: 999px;
            background: #ffd166;
            color: #142044;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 12px 0 #0f172a;
            border: 3px solid #0f172a;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 0 #0f172a;
        }

        .hero-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 24px;
        }

        .card-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 32px;
            max-width: 1300px;
            margin: 0 auto;
        }

        .epic-card {
            background: #d9e3ff;
            border-radius: 24px 24px 40px 24px;
            padding: 32px;
            color: #0f172a;
            border: 3px solid #0f172a;
            box-shadow: 10px 14px 0 #0b1140;
            min-height: 220px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .epic-card .quote {
            font-family: 'Fredoka', 'Poppins', sans-serif;
            font-size: 1.15rem;
            line-height: 1.5;
            margin-bottom: 24px;
        }

        .epic-card .author {
            font-size: 0.95rem;
            color: #1f285b;
            font-weight: 600;
        }

        .epic-card .author a {
            color: #0f62fe;
            text-decoration: none;
            font-weight: 700;
            border-bottom: 2px solid rgba(15, 98, 254, 0.4);
        }

        .no-data {
            grid-column: 1 / -1;
            text-align: center;
            padding: 64px 24px;
            background: #b7f0ff;
            border-radius: 24px;
            border: 3px dashed #0f172a;
            color: #0f172a;
            box-shadow: 8px 12px 0 #0b1140;
        }

        .no-data h2 {
            font-size: 1.8rem;
            margin-bottom: 16px;
        }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 14px 20px;
            background: #bbf7d0;
            color: #0f172a;
            border-radius: 12px;
            font-weight: 600;
            box-shadow: 6px 10px 0 #0b1140;
            border: 3px solid #0f172a;
        }

        @media (max-width: 768px) {
            body { padding: 24px 16px 60px; }
            .meta-row { grid-template-columns: 1fr; }
            .hero-actions {
                gap: 8px;
                margin-top: 18px;
            }
            .hero-actions .btn-submit {
                width: calc(50% - 4px);
                margin-top: 0;
                padding: 12px 10px;
                gap: 6px;
                font-size: 0.92rem;
                line-height: 1.2;
            }
            .hero-actions .btn-submit:last-child {
                width: 100%;
            }
            .card-container { grid-template-columns: 1fr; }
            .epic-card { padding: 24px; }
        }
    </style>
</head>
<body>
    <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 'success'): ?>
        <div class="toast" id="toast-delete">✅ Refleksi berhasil dihapus</div>
        <script>
            setTimeout(function() {
                var toast = document.getElementById('toast-delete');
                if (toast) {
                    toast.style.opacity = '0';
                    toast.style.transition = 'opacity 0.3s ease';
                    setTimeout(function(){ toast.remove(); }, 300);
                }
            }, 3000);
        </script>
    <?php elseif (isset($_GET['submitted']) && $_GET['submitted'] == 'success'): ?>
        <div class="toast" id="toast-submit">🎉 Refleksi berhasil dikirim</div>
        <script>
            setTimeout(function() {
                var toast = document.getElementById('toast-submit');
                if (toast) {
                    toast.style.opacity = '0';
                    toast.style.transition = 'opacity 0.3s ease';
                    setTimeout(function(){ toast.remove(); }, 300);
                }
            }, 3000);
        </script>
    <?php endif; ?>

    <div class="header">
        <h1>Refleksi Mingguan</h1>
        <p>Level up perjalanan mentee dengan AI.</p>
        <div class="hero-actions">
            <a href="form_interactive.php" class="btn-submit">✍️ Form Interaktif</a>
            <a href="form.php" class="btn-submit" style="background:#d9e3ff; color:#0f172a;">📝 Form Klasik</a>
            <a href="form_psikologi.php" class="btn-submit" style="background:#b7f0ff; color:#0f172a;">🧠 Tes Psikologi Singkat</a>
        </div>
    </div>

    <div class="meta-row">
        <div class="meta-card">
            <span class="label">Waktu Sekarang</span>
            <div class="clock">
                <div class="clock-face" role="img" aria-label="Jam analog waktu sekarang, zona waktu <?php echo htmlspecialchars($zona_waktu); ?>">
                    <div class="clock-hand hour" id="jam-jam"></div>
                    <div class="clock-hand minute" id="jam-menit"></div>
                    <div class="clock-hand second" id="jam-detik"></div>
                    <div class="clock-center"></div>
                </div>
                <div class="clock-info">
                    <div class="clock-digital" id="jam-digital">--:--:--</div>
                    <div class="clock-zone">Zona waktu: <?php echo htmlspecialchars($zona_waktu); ?></div>
                </div>
            </div>
        </div>
        <div class="search-card">
            <span class="label">Pencarian</span>
            <form class="search-form" method="get" action="index.php">
                <input type="text" name="q" placeholder="Cari nama, periode, atau isi kalimat epik" value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit">Cari</button>
                <?php if ($search_query !== ''): ?>
                    <a href="index.php">Reset</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="search-summary">
        <?php if ($search_query !== ''): ?>
            Menampilkan hasil pencarian untuk "<?php echo htmlspecialchars($search_query); ?>".
        <?php else: ?>
            Tampilkan semua kartu epik atau gunakan pencarian untuk menyaring hasil.
        <?php endif; ?>
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
                        (<?php echo !empty($row['periode_minggu']) ? 'Minggu ke-' . htmlspecialchars($row['periode_minggu']) : 'Tanpa minggu'; ?>)
                        | <a href="detail.php?id=<?php echo $row['id']; ?>">Lihat Detail</a>
                    </p>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-data">
                <h2>Belum ada Kartu Epik.</h2>
                <p>Silakan isi refleksi baru terlebih dahulu untuk membuatnya.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        (function () {
            var hourHand = document.getElementById('jam-jam');
            var minuteHand = document.getElementById('jam-menit');
            var secondHand = document.getElementById('jam-detik');
            var digital = document.getElementById('jam-digital');
            if (!hourHand || !minuteHand || !secondHand) return;

            var timeFormat = new Intl.DateTimeFormat('en-GB', {
                timeZone: 'Asia/Jakarta',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            });

            function pad(value) {
                return String(value).padStart(2, '0');
            }

            function tick() {
                var parts = timeFormat.formatToParts(new Date());
                var hour = 0, minute = 0, second = 0;

                parts.forEach(function (part) {
                    if (part.type === 'hour') hour = parseInt(part.value, 10) % 24;
                    if (part.type === 'minute') minute = parseInt(part.value, 10);
                    if (part.type === 'second') second = parseInt(part.value, 10);
                });

                secondHand.style.transform = 'rotate(' + (second * 6) + 'deg)';
                minuteHand.style.transform = 'rotate(' + ((minute + second / 60) * 6) + 'deg)';
                hourHand.style.transform = 'rotate(' + (((hour % 12) + minute / 60) * 30) + 'deg)';

                if (digital) {
                    digital.textContent = pad(hour) + ':' + pad(minute) + ':' + pad(second);
                }
            }

            tick();
            setInterval(tick, 1000);
        })();
    </script>
</body>
</html>
