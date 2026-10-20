<?php
// File: form_bebas.php
// Form refleksi satu kolom bebas. AI memecah teks menjadi data terstruktur
// dan memilih varian tabel yang paling cocok dari database.
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/openrouter_helper.php';
require_once __DIR__ . '/schema_helper.php';
require_once __DIR__ . '/github_helper.php';
require_once __DIR__ . '/kata_kata_helper.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$pesan_error = '';
$pesan_sukses = '';
$hasil_tampil = null;
$issue_url = '';
$teks_input = '';

if (!isset($_SESSION['form_bebas_csrf'])) {
    $_SESSION['form_bebas_csrf'] = bin2hex(random_bytes(16));
}
$csrf = (string) $_SESSION['form_bebas_csrf'];

// Ambil flash dari redirect (PRG) agar refresh tidak mengirim ulang.
if (!empty($_SESSION['form_bebas_flash']) && is_array($_SESSION['form_bebas_flash'])) {
    $flash = $_SESSION['form_bebas_flash'];
    unset($_SESSION['form_bebas_flash']);
    $pesan_sukses = (string) ($flash['message'] ?? '');
    $hasil_tampil = $flash['data'] ?? null;
    $issue_url = (string) ($flash['issue_url'] ?? '');
}

function fb_strlen(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s) : strlen($s);
}

function fb_substr(string $s, int $start, int $length): string
{
    return function_exists('mb_substr') ? mb_substr($s, $start, $length) : substr($s, $start, $length);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teks_input = trim((string) ($_POST['teks_bebas'] ?? ''));
    $token = (string) ($_POST['csrf'] ?? '');

    if (!hash_equals($csrf, $token)) {
        $pesan_error = 'Sesi form kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } elseif (fb_strlen($teks_input) < 20) {
        $pesan_error = 'Tulis cerita sedikit lebih lengkap (minimal 20 karakter) agar AI bisa memecahnya.';
    } elseif (fb_strlen($teks_input) > 8000) {
        $pesan_error = 'Teks terlalu panjang. Maksimal 8.000 karakter.';
    } elseif (openrouter_api_key() === '') {
        $pesan_error = 'Kunci OpenRouter belum diatur di .env. Form bebas membutuhkan AI untuk memecah cerita.';
    } else {
        @set_time_limit(180);

        $discovered = schema_discover(true);
        $tables = schema_table_names($discovered);
        if ($tables === []) {
            $pesan_error = 'Tidak ada tabel database yang bisa dipakai. Periksa koneksi database.';
        } else {
            $catalog = schema_catalog_for_prompt($discovered);
            $hariIni = date('l, d F Y');
            $mingguKe = (int) date('W');
            $tahunIni = date('Y');
            $bulanIndo = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            $formatTanggal = static function (int $ts) use ($bulanIndo): string {
                return date('d', $ts) . ' ' . $bulanIndo[(int) date('n', $ts)] . ' ' . date('Y', $ts);
            };
            $tanggalMulai = $formatTanggal((int) strtotime('monday this week'));
            $tanggalAkhir = $formatTanggal((int) strtotime('sunday this week'));
            $rentangTanggal = $tanggalMulai . ' - ' . $tanggalAkhir;
            $system = 'Anda adalah mesin ekstraksi data refleksi mentee. Ubah satu teks bebas menjadi data terstruktur'
                . ' yang cocok dengan salah satu tabel database yang diberikan.'
                . ' Balas HANYA dengan satu objek JSON valid, tanpa markdown/code fence/penjelasan.'
                . ' Bentuk JSON: {"table":"nama_tabel","confidence":0.0,"reason":"alasan singkat","data":{"kolom":nilai},"expected_schema":null}.'
                . ' Aturan wajib:'
                . ' (1) Pilih hanya tabel yang ada di katalog. Jangan mengarang nama tabel.'
                . ' (2) Isi semua kolom bertanda WAJIB. Jangan mengisi kolom AUTO.'
                . ' (3) Pertahankan fakta dari teks; jangan mengarang detail pribadi.'
                . ' (4) Jika tidak ada tabel yang cocok, set table=null, confidence=0, dan isi expected_schema'
                . '     dengan {"table":"usulan_nama","columns":[{"name":"...","type":"...","required":true,"description":"..."}]}.'
                . ' (5) Jika tabel memiliki kolom kalimat_epik, WAJIB isi dengan satu kalimat epik singkat, hangat,'
                . '     dan reflektif dalam Bahasa Indonesia (maksimal 35 kata).'
                . ' (6) Nama mentee/mentor: jika tidak disebut, gunakan "Mentee"/"Mentor".'
                . ' (7) Jika pengguna meminta nama disamarkan, isi nama_disamarkan=1; jika tidak, 0.'
                . ' (8) Skor/level/energi harus angka 1-10, jangan 0.'
                . ' (9) periode_minggu diisi nomor minggu tahun ini; periode_tanggal diisi rentang tanggal minggu ini.'
                . ' (10) Jangan menulis string "now" pada kolom wajib. Gunakan tanggal nyata dari konteks di bawah.'
                . ' (11) Gunakan Bahasa Indonesia yang natural dan konsisten. Jangan mencampur bahasa asing atau karakter aneh.';

            $user = "KONTEKS WAKTU:\n"
                . "Hari ini: {$hariIni}\n"
                . "Minggu ke: {$mingguKe} tahun {$tahunIni}\n"
                . "Rentang minggu ini: {$rentangTanggal}\n\n"
                . "SKEMA DATABASE:\n" . $catalog
                . "\n\nTEKS MENTEE:\n" . $teks_input
                . "\n\nPilih tabel yang paling cocok lalu ekstrak datanya.";

            $ai = openrouter_generate_json($system, $user, null, 8);
            if (!$ai['ok']) {
                $err = rtrim((string) ($ai['error'] ?? 'tidak diketahui'), '. ');
                $pesan_error = 'AI gagal memecah cerita: ' . $err . '. Coba lagi beberapa saat.';
            } else {
                $payload = $ai['data'];
                // Sebagian model membungkus objek dalam array atau mengembalikan
                // field data sebagai string JSON. Normalkan dulu.
                if (isset($payload[0]) && is_array($payload[0])) {
                    $payload = $payload[0];
                }
                if (isset($payload['data']) && is_string($payload['data'])) {
                    $decodedData = json_decode($payload['data'], true);
                    if (is_array($decodedData)) {
                        $payload['data'] = $decodedData;
                    }
                }
                $table = isset($payload['table']) && is_string($payload['table']) ? trim($payload['table']) : '';
                $reason = isset($payload['reason']) && is_scalar($payload['reason']) ? (string) $payload['reason'] : '';
                $expected = $payload['expected_schema'] ?? null;
                $confidence = isset($payload['confidence']) && is_numeric($payload['confidence']) ? (float) $payload['confidence'] : 0.0;

                $hasTable = $table !== '' && in_array($table, $tables, true);
                if (!$hasTable) {
                    // Struktur belum ada: laporkan ke GitHub issue, jangan simpan
                    // ke tabel yang salah.
                    $body = github_build_issue_body($teks_input, is_array($expected) ? $expected : null, $payload, $reason);
                    $title = 'Form bebas: butuh varian tabel/skema baru (' . date('Y-m-d H:i') . ')';
                    $issue = github_create_issue($title, $body, ['form-bebas', 'skema']);
                    if (!empty($issue['ok'])) {
                        $issue_url = (string) ($issue['url'] ?? '');
                        $pesan_error = 'Cerita ini belum cocok dengan varian tabel yang ada. Struktur yang diharapkan sudah dilaporkan ke GitHub issue'
                            . ($issue_url !== '' ? ' (lihat tautan di bawah)' : '') . '.';
                    } else {
                        $pesan_error = 'Cerita ini belum cocok dengan varian tabel yang ada. Laporan sudah diantrikan, tetapi belum terkirim: '
                            . (string) ($issue['error'] ?? 'token GitHub belum diatur') . '.';
                    }
                    $hasil_tampil = $payload;
                } else {
                    $prep = schema_prepare_payload($payload, $discovered);
                    if (!$prep['ok']) {
                        $pesan_error = (string) ($prep['error'] ?? 'Hasil AI tidak valid.');
                        $hasil_tampil = $payload;
                    } else {
                        // Satu kali perbaikan bila ada field wajib yang belum terisi.
                        if (!empty($prep['missing'])) {
                            $repairSystem = 'Anda memperbaiki hasil ekstraksi JSON. Balas HANYA JSON valid dengan bentuk'
                                . ' {"table":"' . $prep['table'] . '","data":{...}}. Jangan mengubah tabel.'
                                . ' Lengkapi semua field wajib yang diminta, pertahankan nilai yang sudah benar,'
                                . ' dan jangan mengarang detail yang tidak ada di teks.';
                            $repairUser = "TEKS MENTEE:\n" . $teks_input
                                . "\n\nSKEMA TABEL " . $prep['table'] . ":\n" . $catalog
                                . "\n\nDATA SEBELUMNYA:\n" . json_encode($prep['data'], JSON_UNESCAPED_UNICODE)
                                . "\n\nFIELD WAJIB YANG BELUM ADA:\n" . implode(', ', $prep['missing'])
                                . "\n\nBalas JSON lengkap yang sudah diperbaiki.";
                            $repair = openrouter_generate_json($repairSystem, $repairUser, $ai['model'] ?? null, 4);
                            if ($repair['ok']) {
                                $repairPayload = $repair['data'];
                                if (isset($repairPayload[0]) && is_array($repairPayload[0])) {
                                    $repairPayload = $repairPayload[0];
                                }
                                $repairData = $repairPayload['data'] ?? $repairPayload;
                                if (is_string($repairData)) {
                                    $decodedRepair = json_decode($repairData, true);
                                    if (is_array($decodedRepair)) {
                                        $repairData = $decodedRepair;
                                    }
                                }
                                if (is_array($repairData)) {
                                    $payload['data'] = array_merge(is_array($payload['data'] ?? null) ? $payload['data'] : [], $repairData);
                                    $prep = schema_prepare_payload($payload, $discovered);
                                }
                            }
                        }

                        if (!$prep['ok']) {
                            $pesan_error = (string) ($prep['error'] ?? 'Hasil AI tidak valid setelah perbaikan.');
                            $hasil_tampil = $payload;
                        } else {
                            $data = $prep['data'];

                            // Lengkapi konteks waktu yang tidak bisa ditebak model.
                            $map = schema_table_map($discovered);
                            $cols = [];
                            foreach (($map[$prep['table']]['columns'] ?? []) as $col) {
                                $cols[(string) $col['name']] = $col;
                            }
                            if (isset($cols['periode_tanggal'])) {
                                $pt = strtolower(trim((string) ($data['periode_tanggal'] ?? '')));
                                if ($pt === '' || $pt === 'now') {
                                    $data['periode_tanggal'] = $rentangTanggal;
                                } else {
                                    // Model kadang memakai nama bulan Inggris. Samakan
                                    // dengan format Indonesia tanpa mengubah tanggalnya.
                                    $data['periode_tanggal'] = str_ireplace(
                                        ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'Jan', 'Feb', 'Mar', 'Apr', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                                        ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember', 'Jan', 'Feb', 'Mar', 'Apr', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'],
                                        (string) $data['periode_tanggal']
                                    );
                                }
                            }
                            if (isset($cols['periode_minggu']) && (int) ($data['periode_minggu'] ?? 0) <= 0) {
                                $data['periode_minggu'] = $mingguKe;
                            }
                            if (isset($cols['skor_energi']) && (int) ($data['skor_energi'] ?? 0) <= 0) {
                                $data['skor_energi'] = 5;
                            }
                            $prep['data'] = $data;
                            $prep['missing'] = array_values(array_filter(
                                $prep['missing'],
                                static fn($m) => !array_key_exists($m, $data)
                            ));

                            if (!empty($prep['missing'])) {
                                $map = schema_table_map($discovered);
                                $requiredCount = 0;
                                foreach (($map[$prep['table']]['columns'] ?? []) as $col) {
                                    if (!empty($col['required'])) {
                                        $requiredCount++;
                                    }
                                }
                                $tooManyMissing = count($prep['missing']) > max(3, (int) ceil($requiredCount * 0.5));
                                if ($tooManyMissing) {
                                    // Jangan simpan baris yang sebagian besar isinya default;
                                    // hasilnya tidak kredibel dan mengotori dashboard.
                                    $pesan_error = 'AI belum melengkapi cukup banyak field wajib ('
                                        . implode(', ', $prep['missing'])
                                        . '). Cerita tidak disimpan agar data tetap kredibel. Coba tulis ulang dengan detail yang lebih lengkap.';
                                    $hasil_tampil = [
                                        'table' => $prep['table'],
                                        'data' => $data,
                                        'missing' => $prep['missing'],
                                        'warnings' => $prep['warnings'],
                                    ];
                                    $data = null;
                                } else {
                                    // Isi aman untuk sisa field wajib yang kosong agar submit
                                    // tidak gagal total; field ini tetap ditandai di hasil.
                                    $data = schema_fill_defaults($data, $prep['missing'], $discovered, $prep['table']);
                                }
                            }

                            if ($data !== null) {
                                // Dashboard menampilkan kalimat_epik. Bila model lupa
                                // mengisinya, buat satu kalimat singkat sebagai pelengkap.
                                $map = schema_table_map($discovered);
                                $hasEpicColumn = false;
                                foreach (($map[$prep['table']]['columns'] ?? []) as $col) {
                                    if (($col['name'] ?? '') === 'kalimat_epik') {
                                        $hasEpicColumn = true;
                                        break;
                                    }
                                }
                                if ($hasEpicColumn) {
                                    $epicValue = trim((string) ($data['kalimat_epik'] ?? ''));
                                    $epicSuspicious = $epicValue === ''
                                        || fb_strlen($epicValue) < 15
                                        || !str_contains($epicValue, ' ')
                                        || str_contains($epicValue, '_')
                                        || preg_match('/^[^a-zA-Z0-9]+$/u', $epicValue) === 1;
                                    if ($epicSuspicious) {
                                        $epicSystem = 'Tulis satu kalimat epik singkat dalam Bahasa Indonesia yang hangat, reflektif,'
                                            . ' tidak menghakimi, dan tanpa diagnosis medis. Balas hanya kalimatnya, maksimal 35 kata.';
                                        $epic = openrouter_generate_text($epicSystem, $teks_input, $ai['model'] ?? null, 3);
                                        if (!empty($epic['ok']) && trim((string) ($epic['text'] ?? '')) !== '') {
                                            $data['kalimat_epik'] = trim((string) $epic['text']);
                                        } elseif ($epicValue === '' || str_contains($epicValue, '_')) {
                                            $fallbackEpic = trim((string) ($data['pembelajaran_baru'] ?? ''));
                                            if ($fallbackEpic === '') {
                                                $fallbackEpic = $teks_input;
                                            }
                                            $data['kalimat_epik'] = fb_substr($fallbackEpic, 0, 140);
                                        }
                                    }
                                }

                                $insert = schema_insert($conn, $prep['table'], $data, $discovered);
                                if ($insert['ok']) {
                                    $id = (int) ($insert['id'] ?? 0);
                                    $message = 'Cerita berhasil dipecah dan disimpan ke tabel "' . $prep['table'] . '"'
                                        . ($id > 0 ? ' (id ' . $id . ')' : '') . '.';
                                    if (!empty($prep['missing'])) {
                                        $message .= ' Catatan: field ' . implode(', ', $prep['missing']) . ' diisi nilai default.';
                                    }
                                    $hasil_tampil = [
                                        'table' => $prep['table'],
                                        'id'    => $id,
                                        'data'  => $data,
                                        'warnings' => $prep['warnings'],
                                        'confidence' => $confidence,
                                    ];
                                    $_SESSION['form_bebas_flash'] = [
                                        'message'   => $message,
                                        'data'      => $hasil_tampil,
                                        'issue_url' => '',
                                    ];
                                    unset($_SESSION['form_bebas_csrf']);
                                    header('Location: form_bebas.php?sukses=1');
                                    exit;
                                }
                                $pesan_error = 'Data gagal disimpan: ' . (string) ($insert['error'] ?? 'error database');
                                $hasil_tampil = ['table' => $prep['table'], 'data' => $data, 'warnings' => $prep['warnings']];
                            }
                        }
                    }
                }
            }
        }
    }
}

$kata = kata_kata_snapshot(8);
$placeholders = $kata['items'];
$needs_refresh = (bool) $kata['needs_refresh'];
$placeholder_awal = $placeholders[0] ?? 'Apa yang paling kamu pelajari minggu ini?';
$csrf_baru = (string) ($_SESSION['form_bebas_csrf'] ?? $csrf);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Bebas - Refleksi Mentee</title>
    <meta name="theme-color" content="#1d2f8a">
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/svg+xml" href="icons/icon-192.svg">
    <link rel="apple-touch-icon" href="icons/icon-192.svg">
    <script src="pwa-register.js" defer></script>
    <script src="audio-player.js" defer></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600&family=Space+Grotesk:wght@400;500&display=swap');

        :root {
            --bg: #1d2f8a;
            --card: #f4f7ff;
            --ink: #0f172a;
            --muted: rgba(15, 23, 42, 0.65);
            --accent: #ffd166;
            --accent-ink: #142044;
            --outline: #0b1140;
            --soft: #dbe5ff;
            --ok: #166534;
            --bad: #b91c1c;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Space Grotesk', 'Poppins', -apple-system, BlinkMacSystemFont, system-ui, sans-serif;
            background:
                radial-gradient(circle at 12% 8%, rgba(78, 115, 255, 0.42), transparent 42%),
                radial-gradient(circle at 88% 0%, rgba(255, 209, 102, 0.12), transparent 38%),
                var(--bg);
            color: #f4f6fb;
            min-height: 100vh;
            padding: 28px 16px 110px;
        }

        .wrap { max-width: 860px; margin: 0 auto; }

        .nav {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
            margin-bottom: 22px;
        }

        .nav a {
            color: #f4f6fb;
            text-decoration: none;
            padding: 9px 14px;
            border-radius: 999px;
            border: 2px solid rgba(255, 255, 255, 0.18);
            background: rgba(9, 15, 40, 0.45);
            font-size: 0.86rem;
        }

        .nav a.active {
            background: var(--accent);
            color: var(--accent-ink);
            border-color: var(--accent);
            font-weight: 700;
        }

        .hero { text-align: center; margin-bottom: 22px; }

        .hero h1 {
            font-family: 'Fredoka', sans-serif;
            font-size: clamp(2rem, 6vw, 2.9rem);
            color: var(--accent);
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .hero p { color: rgba(244, 246, 251, 0.82); }

        .card {
            background: var(--card);
            color: var(--ink);
            border-radius: 28px;
            border: 3px solid var(--outline);
            box-shadow: 14px 18px 0 #0b1140;
            padding: 28px;
        }

        .helper {
            color: var(--muted);
            font-size: 0.92rem;
            line-height: 1.55;
            margin-bottom: 16px;
        }

        label { display: block; font-weight: 700; margin-bottom: 8px; }

        textarea {
            width: 100%;
            min-height: 210px;
            resize: vertical;
            border: 2px solid rgba(15, 23, 42, 0.18);
            border-radius: 18px;
            padding: 16px;
            font: inherit;
            line-height: 1.6;
            color: var(--ink);
            background: #ffffff;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        textarea:focus {
            border-color: #1d4ed8;
            box-shadow: 0 0 0 4px rgba(29, 78, 216, 0.14);
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 8px;
            color: var(--muted);
            font-size: 0.8rem;
        }

        .chips {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 14px;
        }

        .chip {
            border: 1px solid rgba(15, 23, 42, 0.18);
            background: var(--soft);
            color: var(--ink);
            border-radius: 999px;
            padding: 7px 12px;
            font: inherit;
            font-size: 0.8rem;
            cursor: pointer;
            text-align: left;
        }

        .chip:hover { border-color: #1d4ed8; }

        .actions { margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }

        .btn {
            border: 3px solid var(--outline);
            border-radius: 999px;
            padding: 14px 24px;
            background: var(--accent);
            color: var(--accent-ink);
            font: 700 1rem 'Space Grotesk', sans-serif;
            cursor: pointer;
            box-shadow: 0 8px 0 var(--outline);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 0 var(--outline); }
        .btn:active { transform: translateY(2px); box-shadow: 0 5px 0 var(--outline); }
        .btn:disabled { opacity: 0.65; cursor: wait; transform: none; }

        .status { font-size: 0.88rem; color: var(--muted); }

        .success, .error {
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 18px;
            border: 2px solid;
            line-height: 1.5;
        }

        .success { background: #dcfce7; border-color: #86efac; color: var(--ok); }
        .error { background: #fee2e2; border-color: #fca5a5; color: var(--bad); }

        .result {
            margin-top: 20px;
            border-top: 2px dashed rgba(15, 23, 42, 0.18);
            padding-top: 16px;
        }

        .result h2 { font-size: 1.05rem; margin-bottom: 8px; }

        pre {
            background: #0b1140;
            color: #dbe5ff;
            border-radius: 14px;
            padding: 14px;
            overflow: auto;
            font-size: 0.8rem;
            line-height: 1.5;
            max-height: 360px;
        }

        .issue-link { margin-top: 10px; font-size: 0.9rem; }
        .issue-link a { color: #1d4ed8; font-weight: 700; }

        @media (max-width: 640px) {
            body { padding: 18px 12px 120px; }
            .card { padding: 20px; box-shadow: 10px 12px 0 #0b1140; }
            .nav a { font-size: 0.78rem; padding: 7px 10px; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <nav class="nav">
        <a href="index.php">🏠 Dashboard</a>
        <a href="form.php">📝 Form Klasik</a>
        <a href="form_interactive.php">✨ Form Interaktif</a>
        <a href="form_psikologi.php">🧠 Tes Psikologi</a>
        <a href="form_bebas.php" class="active">🪶 Form Bebas</a>
    </nav>

    <div class="hero">
        <h1>Form Bebas</h1>
        <p>Satu kolom. Tulis apa saja, AI akan memecahnya menjadi data terstruktur.</p>
    </div>

    <div class="card">
        <?php if ($pesan_sukses !== ''): ?>
            <div class="success">✅ <?php echo htmlspecialchars($pesan_sukses); ?></div>
        <?php endif; ?>
        <?php if ($pesan_error !== ''): ?>
            <div class="error">⚠️ <?php echo htmlspecialchars($pesan_error); ?></div>
            <?php if ($issue_url !== ''): ?>
                <p class="issue-link">Tautan laporan: <a href="<?php echo htmlspecialchars($issue_url); ?>" target="_blank" rel="noopener">buka GitHub issue</a></p>
            <?php endif; ?>
        <?php endif; ?>

        <p class="helper">
            Ceritakan minggu ini dengan bahasamu sendiri. Bisa tentang pembelajaran, perasaan,
            tantangan, relasi, atau hal kecil yang berkesan. AI akan memilih varian tabel yang
            paling cocok. Jika belum ada struktur yang cocok, hasil ekstraksi dan cuplikan teks
            yang sudah disamarkan (email/nomor/tautan) akan dilaporkan ke GitHub issue agar bisa
            ditambahkan.
        </p>

        <form method="post" action="form_bebas.php" id="form-bebas">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf_baru); ?>">
            <label for="teks_bebas">Ceritamu</label>
            <textarea id="teks_bebas" name="teks_bebas" maxlength="8000" required
                placeholder="<?php echo htmlspecialchars($placeholder_awal); ?>"><?php echo htmlspecialchars($teks_input); ?></textarea>

            <div class="meta-row">
                <span>Minimal 20 karakter. Makin jujur, makin kaya hasil ekstraksinya.</span>
                <span id="char-count">0 / 8000</span>
            </div>

            <div class="chips" id="chips">
                <?php foreach ($placeholders as $p): ?>
                    <button type="button" class="chip" data-text="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></button>
                <?php endforeach; ?>
            </div>

            <div class="actions">
                <button type="submit" class="btn" id="btn-submit">🔎 Analisis &amp; Simpan</button>
                <span class="status" id="status">Belum dianalisis.</span>
            </div>
        </form>

        <?php if ($hasil_tampil !== null): ?>
            <div class="result">
                <h2>Hasil ekstraksi</h2>
                <?php if (is_array($hasil_tampil) && !empty($hasil_tampil['table'])): ?>
                    <p class="helper">Tabel: <strong><?php echo htmlspecialchars((string) $hasil_tampil['table']); ?></strong>
                        <?php if (!empty($hasil_tampil['confidence'])): ?>
                            · keyakinan <?php echo htmlspecialchars((string) round((float) $hasil_tampil['confidence'] * 100)); ?>%
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
                <pre><?php echo htmlspecialchars(json_encode($hasil_tampil, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></pre>
            </div>
        <?php endif; ?>
    </div>
</div>

<script type="application/json" id="placeholders-data"><?php echo json_encode($placeholders, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
<script>
(function () {
    const textarea = document.getElementById('teks_bebas');
    const counter = document.getElementById('char-count');
    const status = document.getElementById('status');
    const form = document.getElementById('form-bebas');
    const btn = document.getElementById('btn-submit');
    const chips = document.getElementById('chips');

    function updateCount() {
        counter.textContent = textarea.value.length + ' / 8000';
    }
    textarea.addEventListener('input', updateCount);
    updateCount();

    // Rotasi placeholder dari cache kata-kata.json.
    let list = [];
    try {
        list = JSON.parse(document.getElementById('placeholders-data').textContent || '[]');
    } catch (e) { list = []; }
    let index = 0;
    if (list.length > 1) {
        setInterval(function () {
            if (textarea.value.trim() !== '') return;
            index = (index + 1) % list.length;
            textarea.placeholder = list[index];
        }, 7000);
    }

    if (chips) {
        chips.addEventListener('click', function (event) {
            const chip = event.target.closest('.chip');
            if (!chip) return;
            const text = chip.dataset.text || '';
            const current = textarea.value.trim();
            textarea.value = current === '' ? text : current + '\n\n' + text;
            textarea.focus();
            updateCount();
        });
    }

    form.addEventListener('submit', function () {
        if (textarea.value.trim().length < 20) return;
        btn.disabled = true;
        status.textContent = 'AI sedang membaca dan memecah ceritamu...';
    });

    // Jika cache kata-kata kedaluwarsa, minta server menyegarkan di belakang layar.
    const needsRefresh = <?php echo $needs_refresh ? 'true' : 'false'; ?>;
    if (needsRefresh) {
        setTimeout(function () {
            fetch('kata-kata-refresh.php', { method: 'POST', headers: { 'Accept': 'application/json' } })
                .catch(function () {});
        }, 1800);
    }
})();
</script>
</body>
</html>
