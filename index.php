<?php
require_once 'env.php';
require_once 'clock_helper.php';

// Waktu server (PHP & MySQL) untuk kartu "Waktu Sekarang".
$waktu = baca_waktu_server($conn);

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

        .clock-compare {
            list-style: none;
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            display: grid;
            gap: 8px;
        }

        .clock-compare li {
            display: grid;
            grid-template-columns: 54px minmax(74px, auto) 1fr auto;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
        }

        .cmp-name {
            font-size: 0.7rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.55);
        }

        .cmp-time {
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            color: #ffffff;
        }

        .cmp-zone {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.55);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cmp-delta {
            font-size: 0.72rem;
            font-variant-numeric: tabular-nums;
            padding: 2px 8px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            color: rgba(255, 255, 255, 0.7);
            white-space: nowrap;
        }

        .cmp-delta.ok   { background: rgba(74, 222, 128, 0.16); color: #86efac; }
        .cmp-delta.warn { background: rgba(251, 191, 36, 0.16); color: #fcd34d; }
        .cmp-delta.bad  { background: rgba(248, 113, 113, 0.18); color: #fca5a5; }

        .cmp-zone.bad {
            color: #fca5a5;
        }

        /* Sembunyikan rincian bila waktu & zona ketiganya sudah sinkron. */
        .meta-card.synced .clock-compare {
            display: none;
        }

        .clock-status {
            margin-top: 12px;
            font-size: 0.74rem;
            color: rgba(255, 255, 255, 0.55);
        }

        .clock-status.ok {
            color: #86efac;
        }

        .clock-status.warn {
            color: #fcd34d;
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
        <div class="meta-card" id="kartu-waktu">
            <span class="label">Waktu Sekarang</span>
            <div class="clock">
                <div class="clock-face" role="img" aria-label="Jam analog waktu server PHP, zona waktu <?php echo htmlspecialchars($waktu['php']['tz']); ?>">
                    <div class="clock-hand hour" id="jam-jam"></div>
                    <div class="clock-hand minute" id="jam-menit"></div>
                    <div class="clock-hand second" id="jam-detik"></div>
                    <div class="clock-center"></div>
                </div>
                <div class="clock-info">
                    <div class="clock-digital" id="jam-digital"><?php echo htmlspecialchars($waktu['php']['time']); ?></div>
                    <div class="clock-zone">PHP · <span id="zona-php"><?php echo htmlspecialchars($waktu['php']['tz']); ?></span> · <?php echo htmlspecialchars($waktu['php']['offset_label']); ?></div>
                </div>
            </div>
            <ul class="clock-compare">
                <li>
                    <span class="cmp-name">MySQL</span>
                    <span class="cmp-time" id="jam-mysql"><?php echo $waktu['mysql'] ? htmlspecialchars($waktu['mysql']['time']) : '—'; ?></span>
                    <span class="cmp-zone" id="zona-mysql" title="<?php echo $waktu['mysql'] ? htmlspecialchars($waktu['mysql']['tz']) : ''; ?>"><?php echo $waktu['mysql'] ? htmlspecialchars($waktu['mysql']['tz_label']) : 'tidak tersedia'; ?></span>
                    <span class="cmp-delta <?php echo kelas_selisih($waktu['mysql_selisih']); ?>" id="selisih-mysql"><?php echo htmlspecialchars(format_selisih($waktu['mysql_selisih'])); ?></span>
                </li>
                <li>
                    <span class="cmp-name">Anda</span>
                    <span class="cmp-time" id="jam-user">—</span>
                    <span class="cmp-zone" id="zona-user">lokal</span>
                    <span class="cmp-delta" id="selisih-user">—</span>
                </li>
            </ul>
            <div class="clock-status" id="clock-status">Memeriksa sinkronisasi waktu &amp; zona…</div>
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
            var card = document.getElementById('kartu-waktu');
            var hourHand = document.getElementById('jam-jam');
            var minuteHand = document.getElementById('jam-menit');
            var secondHand = document.getElementById('jam-detik');
            var digital = document.getElementById('jam-digital');
            var zonaPhp = document.getElementById('zona-php');
            var mysqlTime = document.getElementById('jam-mysql');
            var mysqlZone = document.getElementById('zona-mysql');
            var mysqlDelta = document.getElementById('selisih-mysql');
            var userTime = document.getElementById('jam-user');
            var userZone = document.getElementById('zona-user');
            var userDelta = document.getElementById('selisih-user');
            var status = document.getElementById('clock-status');

            if (!card || !hourHand || !minuteHand || !secondHand) return;

            // Waktu server saat halaman dibuat (sumber jam, bukan jam browser).
            var initialServerMs = <?php echo (int) $waktu['php']['epoch_ms']; ?>;
            var serverOffsetMs = initialServerMs - Date.now();

            var phpOffset = <?php echo (int) $waktu['php']['offset']; ?>;
            var latestPhpEpoch = <?php echo json_encode((float) $waktu['php']['epoch']); ?>;
            var latestMysqlEpoch = <?php echo json_encode($waktu['mysql'] ? (int) $waktu['mysql']['epoch'] : null); ?>;
            var latestMysqlOffset = <?php echo json_encode($waktu['mysql'] ? (int) $waktu['mysql']['utc_offset'] : null); ?>;

            var phpFormat = new Intl.DateTimeFormat('en-GB', {
                timeZone: <?php echo json_encode($waktu['php']['tz']); ?>,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            });

            var userFormat = new Intl.DateTimeFormat('en-GB', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            });

            var userTzName = 'lokal';
            try {
                userTzName = Intl.DateTimeFormat().resolvedOptions().timeZone || 'lokal';
            } catch (e) {
                userTzName = 'lokal';
            }

            var mysqlState = null;
            var initialMysql = <?php echo json_encode($waktu['mysql'] ? [
                'time'  => $waktu['mysql']['time'],
                'epoch' => $waktu['mysql']['epoch'],
            ] : null); ?>;

            if (initialMysql) {
                var im = initialMysql.time.split(':');
                mysqlState = {
                    baseSeconds: parseInt(im[0], 10) * 3600 + parseInt(im[1], 10) * 60 + parseInt(im[2], 10),
                    baseAt: initialServerMs
                };
            }

            function pad2(value) {
                return String(value).padStart(2, '0');
            }

            function serverNowMs() {
                return Date.now() + serverOffsetMs;
            }

            function userOffset() {
                return -new Date().getTimezoneOffset() * 60;
            }

            function labelOffset(seconds) {
                if (seconds === null || seconds === undefined || isNaN(seconds)) {
                    return '?';
                }
                var sign = seconds < 0 ? '-' : '+';
                var abs = Math.abs(seconds);
                return 'UTC' + sign + pad2(Math.floor(abs / 3600)) + ':' + pad2(Math.floor((abs % 3600) / 60));
            }

            function ambilBagianWaktu(date, formatter) {
                var hasil = { hour: 0, minute: 0, second: 0 };
                formatter.formatToParts(date).forEach(function (part) {
                    if (part.type === 'hour') hasil.hour = parseInt(part.value, 10) % 24;
                    if (part.type === 'minute') hasil.minute = parseInt(part.value, 10);
                    if (part.type === 'second') hasil.second = parseInt(part.value, 10);
                });
                return hasil;
            }

            function formatSelisih(detik) {
                var d = Math.round(detik);
                var abs = Math.abs(d);
                var sign = d > 0 ? '+' : (d < 0 ? '-' : '');

                if (abs < 60) return (d === 0 ? '0' : sign + abs) + ' dtk';
                if (abs < 3600) return sign + (abs / 60).toFixed(1).replace(/\.0$/, '') + ' mnt';
                return sign + (abs / 3600).toFixed(1).replace(/\.0$/, '') + ' jam';
            }

            function kelasSelisih(detik) {
                var abs = Math.abs(detik);
                if (abs <= 2) return 'ok';
                if (abs <= 10) return 'warn';
                return 'bad';
            }

            function setSelisih(el, detik) {
                if (detik === null || isNaN(detik)) {
                    el.textContent = '—';
                    el.className = 'cmp-delta';
                    return;
                }

                var d = Math.round(detik);
                el.textContent = formatSelisih(d);
                el.className = 'cmp-delta ' + kelasSelisih(d);
            }

            function selisihMysql() {
                if (latestMysqlEpoch === null) return null;
                return latestMysqlEpoch - latestPhpEpoch;
            }

            function selisihUser() {
                return (Date.now() - serverNowMs()) / 1000;
            }

            function zonaSinkron() {
                return latestMysqlOffset !== null
                    && latestMysqlOffset === phpOffset
                    && userOffset() === phpOffset;
            }

            function waktuSinkron() {
                var dMysql = selisihMysql();
                if (dMysql === null) return false;
                return Math.abs(Math.round(dMysql)) <= 2
                    && Math.abs(Math.round(selisihUser())) <= 2;
            }

            function perbaruiStatus() {
                var sinkronZona = zonaSinkron();
                var sinkronWaktu = waktuSinkron();
                var sinkron = sinkronZona && sinkronWaktu;

                // Rincian hanya ditampilkan bila ada yang belum sinkron.
                card.classList.toggle('synced', sinkron);

                if (sinkron) {
                    status.textContent = '✓ PHP, MySQL, dan zona waktu sinkron';
                    status.className = 'clock-status ok';
                    return;
                }

                var masalah = [];

                if (latestMysqlOffset === null) {
                    masalah.push('waktu MySQL tidak tersedia');
                } else if (!sinkronZona) {
                    masalah.push(
                        'zona berbeda: PHP ' + labelOffset(phpOffset)
                        + ' · MySQL ' + labelOffset(latestMysqlOffset)
                        + ' · Anda ' + labelOffset(userOffset())
                    );
                }

                if (!sinkronWaktu && latestMysqlEpoch !== null) {
                    masalah.push('selisih waktu > 2 dtk');
                }

                status.textContent = '⚠ ' + masalah.join(' · ');
                status.className = 'clock-status warn';
            }

            function renderPhp() {
                var now = new Date(serverNowMs());
                var t = ambilBagianWaktu(now, phpFormat);

                secondHand.style.transform = 'rotate(' + (t.second * 6) + 'deg)';
                minuteHand.style.transform = 'rotate(' + ((t.minute + t.second / 60) * 6) + 'deg)';
                hourHand.style.transform = 'rotate(' + (((t.hour % 12) + t.minute / 60) * 30) + 'deg)';
                digital.textContent = pad2(t.hour) + ':' + pad2(t.minute) + ':' + pad2(t.second);
            }

            function renderMysql() {
                if (!mysqlState) return;

                var elapsed = Math.floor((serverNowMs() - mysqlState.baseAt) / 1000);
                var total = (mysqlState.baseSeconds + elapsed) % 86400;
                if (total < 0) total += 86400;

                mysqlTime.textContent = pad2(Math.floor(total / 3600))
                    + ':' + pad2(Math.floor((total % 3600) / 60))
                    + ':' + pad2(total % 60);
            }

            function renderUser() {
                var now = new Date();
                var t = ambilBagianWaktu(now, userFormat);

                userTime.textContent = pad2(t.hour) + ':' + pad2(t.minute) + ':' + pad2(t.second);
                userZone.textContent = userTzName + ' (' + labelOffset(userOffset()) + ')';
                userZone.title = userTzName;

                mysqlZone.classList.toggle('bad', latestMysqlOffset !== null && latestMysqlOffset !== phpOffset);
                userZone.classList.toggle('bad', userOffset() !== phpOffset);

                setSelisih(mysqlDelta, selisihMysql());
                setSelisih(userDelta, selisihUser());
            }

            function renderAll() {
                renderPhp();
                renderMysql();
                renderUser();
                perbaruiStatus();
            }

            function syncTime() {
                var started = Date.now();

                fetch('time.php?t=' + started, { cache: 'no-store' })
                    .then(function (response) {
                        if (!response.ok) throw new Error('HTTP ' + response.status);
                        return response.json();
                    })
                    .then(function (data) {
                        var finished = Date.now();

                        if (data.php && data.php.epoch) {
                            // Kompensasi latensi jaringan (asumsi simetris).
                            serverOffsetMs = (data.php.epoch * 1000) - ((started + finished) / 2);
                            latestPhpEpoch = data.php.epoch;
                            phpOffset = data.php.offset;
                        }

                        if (data.php && data.php.tz && zonaPhp) {
                            zonaPhp.textContent = data.php.tz;
                        }

                        if (data.mysql) {
                            var p = data.mysql.time.split(':');
                            mysqlState = {
                                baseSeconds: parseInt(p[0], 10) * 3600 + parseInt(p[1], 10) * 60 + parseInt(p[2], 10),
                                baseAt: data.php.epoch * 1000
                            };
                            latestMysqlEpoch = data.mysql.epoch;
                            latestMysqlOffset = data.mysql.utc_offset;
                            mysqlZone.textContent = data.mysql.tz_label || data.mysql.tz || '—';
                            mysqlZone.title = data.mysql.tz || '';
                        } else {
                            mysqlState = null;
                            latestMysqlEpoch = null;
                            latestMysqlOffset = null;
                            mysqlTime.textContent = '—';
                            mysqlZone.textContent = 'tidak tersedia';
                            mysqlZone.classList.remove('bad');
                        }

                        renderAll();
                    })
                    .catch(function () {
                        status.textContent = '⚠ Gagal membaca waktu server; menampilkan data awal.';
                        status.className = 'clock-status warn';
                    });
            }

            renderAll();
            syncTime();
            setInterval(renderAll, 1000);
            setInterval(syncTime, 30000);

            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) syncTime();
            });
        })();
    </script>
</body>
</html>
