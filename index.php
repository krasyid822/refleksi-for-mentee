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
    <script>
        // Terapkan tema sebelum CSS dirender agar tidak ada kedipan.
        (function () {
            var theme = 'light';
            try {
                var saved = localStorage.getItem('refleksi-theme');
                if (saved === 'dark' || saved === 'light') theme = saved;
            } catch (e) {}
            document.documentElement.dataset.theme = theme;
            var meta = document.querySelector('meta[name="theme-color"]');
            if (meta) meta.setAttribute('content', theme === 'dark' ? '#05070f' : '#1d2f8a');
        })();
    </script>
    <link rel="manifest" href="manifest.json">
    <link rel="stylesheet" href="page-transition.css">
    <link rel="icon" type="image/svg+xml" href="icons/icon-192.svg">
    <link rel="apple-touch-icon" href="icons/icon-192.svg">
    <script src="page-transition.js" defer></script>
    <script src="pwa-register.js" defer></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600&family=Space+Grotesk:wght@400;500&display=swap');

        /* ============================================================
           Variabel tema: terang (default) & gelap
           ============================================================ */
        :root {
            color-scheme: light;

            --bg: #1d2f8a;
            --glow-1: transparent;
            --glow-2: transparent;
            --glow-3: transparent;

            --text: #f4f6fb;
            --text-strong: #ffffff;
            --muted: rgba(255, 255, 255, 0.7);
            --muted-strong: rgba(255, 255, 255, 0.86);
            --label: rgba(255, 255, 255, 0.58);
            --heading: #ffd166;

            --panel: rgba(9, 15, 40, 0.72);
            --panel-border: rgba(255, 255, 255, 0.12);
            --panel-shadow: rgba(0, 0, 0, 0.24);
            --divider: rgba(255, 255, 255, 0.12);

            --accent: #ffd166;
            --accent-text: #142044;
            --danger: #ff6b6b;

            --clock-face: rgba(9, 15, 40, 0.92);
            --clock-border: rgba(255, 255, 255, 0.24);
            --clock-shadow: rgba(0, 0, 0, 0.5);
            --tick: rgba(255, 255, 255, 0.55);
            --hand-minute: #f4f6fb;

            --input-bg: rgba(255, 255, 255, 0.06);
            --input-border: rgba(255, 255, 255, 0.16);
            --input-text: #ffffff;
            --placeholder: rgba(255, 255, 255, 0.55);

            --chip-bg: rgba(255, 255, 255, 0.08);
            --ok-bg: rgba(74, 222, 128, 0.16);
            --ok-fg: #86efac;
            --warn-bg: rgba(251, 191, 36, 0.16);
            --warn-fg: #fcd34d;
            --bad-bg: rgba(248, 113, 113, 0.18);
            --bad-fg: #fca5a5;

            --btn-shadow: #0f172a;
            --btn-border: #0f172a;
            --btn-2-bg: #d9e3ff;
            --btn-2-fg: #0f172a;
            --btn-3-bg: #b7f0ff;
            --btn-3-fg: #0f172a;
            --btn-4-bg: #bbf7d0;
            --btn-4-fg: #0f172a;

            --card-bg: #d9e3ff;
            --card-text: #0f172a;
            --card-border: #0f172a;
            --card-shadow: #0b1140;
            --card-glow: transparent;
            --card-author: #1f285b;
            --link: #0f62fe;
            --link-border: rgba(15, 98, 254, 0.4);

            --nodata-bg: #b7f0ff;
            --toast-bg: #bbf7d0;
            --toast-fg: #0f172a;

            --theme-icon: #142044;
            --theme-btn-bg: rgba(255, 255, 255, 0.9);
            --theme-btn-border: rgba(15, 23, 42, 0.16);
        }

        html[data-theme="dark"] {
            color-scheme: dark;

            --bg: #05070f;
            --glow-1: rgba(56, 89, 255, 0.22);
            --glow-2: rgba(125, 211, 252, 0.10);
            --glow-3: rgba(124, 58, 237, 0.18);

            --text: #e6ebff;
            --text-strong: #f7f9ff;
            --muted: rgba(203, 213, 255, 0.62);
            --muted-strong: rgba(226, 232, 255, 0.8);
            --label: rgba(203, 213, 255, 0.55);
            --heading: #ffe08a;

            --panel: rgba(10, 15, 30, 0.82);
            --panel-border: rgba(125, 211, 252, 0.18);
            --panel-shadow: rgba(0, 0, 0, 0.55);
            --divider: rgba(125, 211, 252, 0.16);

            --accent: #ffd166;
            --accent-text: #0b1020;
            --danger: #ff7a8a;

            --clock-face: rgba(2, 5, 12, 0.96);
            --clock-border: rgba(125, 211, 252, 0.35);
            --clock-shadow: rgba(0, 0, 0, 0.75);
            --tick: rgba(186, 230, 253, 0.55);
            --hand-minute: #e6ebff;

            --input-bg: rgba(255, 255, 255, 0.05);
            --input-border: rgba(125, 211, 252, 0.2);
            --input-text: #eaf2ff;
            --placeholder: rgba(186, 210, 255, 0.45);

            --chip-bg: rgba(125, 211, 252, 0.1);
            --ok-bg: rgba(74, 222, 128, 0.14);
            --ok-fg: #6ee7a0;
            --warn-bg: rgba(251, 191, 36, 0.14);
            --warn-fg: #fbbf24;
            --bad-bg: rgba(248, 113, 113, 0.16);
            --bad-fg: #f87171;

            --btn-shadow: #010206;
            --btn-border: #010206;
            --btn-2-bg: #1b2547;
            --btn-2-fg: #e6ebff;
            --btn-3-bg: #123047;
            --btn-3-fg: #d7f6ff;
            --btn-4-bg: #133a2a;
            --btn-4-fg: #bbf7d0;

            --card-bg: #0f1630;
            --card-text: #e6ebff;
            --card-border: #2b3a6b;
            --card-shadow: #010206;
            --card-glow: rgba(125, 211, 252, 0.08);
            --card-author: #a9b8ff;
            --link: #7dd3fc;
            --link-border: rgba(125, 211, 252, 0.45);

            --nodata-bg: #0d1730;
            --toast-bg: #123524;
            --toast-fg: #bbf7d0;

            --theme-icon: #ffd166;
            --theme-btn-bg: rgba(10, 15, 30, 0.85);
            --theme-btn-border: rgba(125, 211, 252, 0.3);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html {
            background-color: var(--bg);
            transition: background-color 0.45s ease;
        }

        body {
            font-family: 'Space Grotesk', 'Poppins', -apple-system, BlinkMacSystemFont, system-ui, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 32px 24px 80px;
            overflow-x: hidden;
            transition: background-color 0.45s ease, color 0.45s ease;
        }

        /* Cahaya latar: netral di tema terang, dramatis di tema gelap. */
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: -1;
            pointer-events: none;
            background:
                radial-gradient(circle at 12% 8%, var(--glow-1), transparent 45%),
                radial-gradient(circle at 88% 2%, var(--glow-2), transparent 40%),
                radial-gradient(circle at 50% 105%, var(--glow-3), transparent 58%);
        }

        .header {
            text-align: center;
            margin-bottom: 48px;
        }

        .header h1 {
            font-family: 'Fredoka', 'Poppins', sans-serif;
            font-size: clamp(2.4rem, 6vw, 3.4rem);
            color: var(--heading);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 12px;
            transition: color 0.45s ease, text-shadow 0.45s ease;
        }

        html[data-theme="dark"] .header h1 {
            text-shadow: 0 0 34px rgba(255, 209, 102, 0.22);
        }

        .header p {
            font-size: 1.1rem;
            color: var(--muted-strong);
            transition: color 0.45s ease;
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
            background: var(--panel);
            border: 2px solid var(--panel-border);
            border-radius: 24px;
            padding: 18px 20px;
            box-shadow: 0 14px 30px var(--panel-shadow);
            backdrop-filter: blur(10px);
            transition: background 0.45s ease, border-color 0.45s ease, box-shadow 0.45s ease;
        }

        .meta-card .label,
        .search-card .label {
            display: block;
            font-size: 0.84rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--label);
            margin-bottom: 8px;
            transition: color 0.45s ease;
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
                var(--clock-face);
            border: 2px solid var(--clock-border);
            box-shadow: inset 0 0 26px var(--clock-shadow), 0 10px 24px var(--panel-shadow);
            transition: background 0.45s ease, border-color 0.45s ease, box-shadow 0.45s ease;
        }

        .clock-face::before {
            content: "";
            position: absolute;
            inset: 7px;
            border-radius: 50%;
            background: repeating-conic-gradient(from -0.5deg, var(--tick) 0 1.2deg, transparent 1.2deg 30deg);
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
            background: var(--accent);
        }

        .clock-hand.minute {
            width: 4px;
            height: 48px;
            margin-left: -2px;
            background: var(--hand-minute);
        }

        .clock-hand.second {
            width: 2px;
            height: 56px;
            margin-left: -1px;
            background: var(--danger);
            box-shadow: 0 0 8px var(--danger);
        }

        .clock-center {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 11px;
            height: 11px;
            margin: -5.5px 0 0 -5.5px;
            border-radius: 50%;
            background: var(--accent);
            box-shadow: 0 0 0 3px var(--clock-face);
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
            color: var(--text-strong);
            transition: color 0.45s ease;
        }

        html[data-theme="dark"] .clock-digital {
            text-shadow: 0 0 18px rgba(125, 211, 252, 0.35);
        }

        .clock-zone {
            font-size: 0.82rem;
            color: var(--muted);
            transition: color 0.45s ease;
        }

        .clock-compare {
            list-style: none;
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid var(--divider);
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
            color: var(--muted);
        }

        .cmp-time {
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            color: var(--text-strong);
        }

        .cmp-zone {
            font-size: 0.75rem;
            color: var(--muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cmp-delta {
            font-size: 0.72rem;
            font-variant-numeric: tabular-nums;
            padding: 2px 8px;
            border-radius: 999px;
            background: var(--chip-bg);
            color: var(--muted);
            white-space: nowrap;
        }

        .cmp-delta.ok   { background: var(--ok-bg); color: var(--ok-fg); }
        .cmp-delta.warn { background: var(--warn-bg); color: var(--warn-fg); }
        .cmp-delta.bad  { background: var(--bad-bg); color: var(--bad-fg); }

        .cmp-zone.bad {
            color: var(--bad-fg);
        }

        /* Sembunyikan rincian bila waktu & zona ketiganya sudah sinkron. */
        .meta-card.synced .clock-compare {
            display: none;
        }

        .clock-status {
            margin-top: 12px;
            font-size: 0.74rem;
            color: var(--muted);
        }

        .clock-status.ok {
            color: var(--ok-fg);
        }

        .clock-status.warn {
            color: var(--warn-fg);
        }

        .search-form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .search-form input {
            flex: 1;
            min-width: 180px;
            border: 2px solid var(--input-border);
            border-radius: 999px;
            padding: 14px 18px;
            background: var(--input-bg);
            color: var(--input-text);
            font: inherit;
            transition: background 0.45s ease, border-color 0.45s ease, color 0.45s ease;
        }

        .search-form input::placeholder { color: var(--placeholder); }

        .search-form button {
            border: 0;
            border-radius: 999px;
            padding: 14px 20px;
            background: var(--accent);
            color: var(--accent-text);
            font-weight: 700;
            cursor: pointer;
            transition: background 0.45s ease, color 0.45s ease;
        }

        .search-form a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 14px 18px;
            border-radius: 999px;
            border: 2px solid var(--input-border);
            color: var(--text-strong);
            text-decoration: none;
            background: var(--input-bg);
            transition: background 0.45s ease, border-color 0.45s ease, color 0.45s ease;
        }

        .search-summary {
            max-width: 1300px;
            margin: 0 auto 18px;
            color: var(--muted-strong);
            font-size: 0.98rem;
            transition: color 0.45s ease;
        }

        .btn-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 24px;
            padding: 16px 32px;
            border-radius: 999px;
            background: var(--accent);
            color: var(--accent-text);
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 12px 0 var(--btn-shadow);
            border: 3px solid var(--btn-border);
            transition: transform 0.2s ease, box-shadow 0.2s ease,
                        background 0.45s ease, color 0.45s ease, border-color 0.45s ease;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 16px 0 var(--btn-shadow);
        }

        .btn-submit.btn-secondary {
            background: var(--btn-2-bg);
            color: var(--btn-2-fg);
        }

        .btn-submit.btn-tertiary {
            background: var(--btn-3-bg);
            color: var(--btn-3-fg);
        }

        .btn-submit.btn-quaternary {
            background: var(--btn-4-bg);
            color: var(--btn-4-fg);
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
            background: var(--card-bg);
            border-radius: 24px 24px 40px 24px;
            padding: 32px;
            color: var(--card-text);
            border: 3px solid var(--card-border);
            box-shadow: 10px 14px 0 var(--card-shadow), 0 0 40px var(--card-glow);
            min-height: 220px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: background 0.45s ease, color 0.45s ease,
                        border-color 0.45s ease, box-shadow 0.45s ease;
        }

        .epic-card .quote {
            font-family: 'Fredoka', 'Poppins', sans-serif;
            font-size: 1.15rem;
            line-height: 1.5;
            margin-bottom: 24px;
        }

        .epic-card .author {
            font-size: 0.95rem;
            color: var(--card-author);
            font-weight: 600;
            transition: color 0.45s ease;
        }

        .epic-card .author a {
            color: var(--link);
            text-decoration: none;
            font-weight: 700;
            border-bottom: 2px solid var(--link-border);
            transition: color 0.45s ease, border-color 0.45s ease;
        }

        .no-data {
            grid-column: 1 / -1;
            text-align: center;
            padding: 64px 24px;
            background: var(--nodata-bg);
            border-radius: 24px;
            border: 3px dashed var(--card-border);
            color: var(--card-text);
            box-shadow: 8px 12px 0 var(--card-shadow), 0 0 40px var(--card-glow);
            transition: background 0.45s ease, color 0.45s ease,
                        border-color 0.45s ease, box-shadow 0.45s ease;
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
            background: var(--toast-bg);
            color: var(--toast-fg);
            border-radius: 12px;
            font-weight: 600;
            box-shadow: 6px 10px 0 var(--card-shadow);
            border: 3px solid var(--card-border);
            transition: background 0.45s ease, color 0.45s ease,
                        border-color 0.45s ease, box-shadow 0.45s ease;
        }

        /* ============================================================
           Tombol tema
           ============================================================ */
        .theme-toggle {
            position: fixed;
            right: 22px;
            bottom: 22px;
            z-index: 9999;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            border: 2px solid var(--theme-btn-border);
            background: var(--theme-btn-bg);
            color: var(--theme-icon);
            cursor: pointer;
            display: grid;
            place-items: center;
            box-shadow: 0 12px 30px var(--panel-shadow);
            backdrop-filter: blur(10px);
            transition: transform 0.25s ease, background 0.45s ease,
                        color 0.45s ease, border-color 0.45s ease, box-shadow 0.45s ease;
        }

        .theme-toggle:hover {
            transform: translateY(-3px) scale(1.05);
        }

        .theme-toggle:active {
            transform: scale(0.94);
        }

        .theme-toggle:focus-visible {
            outline: 3px solid var(--accent);
            outline-offset: 4px;
        }

        .theme-toggle::before {
            content: "";
            position: absolute;
            inset: -6px;
            border-radius: 50%;
            border: 2px solid var(--accent);
            opacity: 0;
            pointer-events: none;
        }

        .theme-toggle.burst::before {
            animation: theme-burst 0.7s ease-out;
        }

        @keyframes theme-burst {
            0%   { transform: scale(0.7); opacity: 0.9; }
            100% { transform: scale(2.4); opacity: 0; }
        }

        .theme-icon {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            transition: transform 0.7s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.35s ease;
        }

        .theme-icon svg {
            width: 24px;
            height: 24px;
        }

        html[data-theme="light"] .theme-icon-sun  { opacity: 0; transform: rotate(-90deg) scale(0.35); }
        html[data-theme="light"] .theme-icon-moon { opacity: 1; transform: rotate(0deg) scale(1); }
        html[data-theme="dark"]  .theme-icon-sun  { opacity: 1; transform: rotate(0deg) scale(1); }
        html[data-theme="dark"]  .theme-icon-moon { opacity: 0; transform: rotate(90deg) scale(0.35); }

        /* ============================================================
           Transisi tema (View Transitions + fallback sweep)
           ============================================================ */
        ::view-transition-old(root),
        ::view-transition-new(root) {
            animation: none;
            mix-blend-mode: normal;
        }

        ::view-transition-old(root) { z-index: 1; }
        ::view-transition-new(root) { z-index: 2; }

        .theme-sweep {
            position: fixed;
            inset: 0;
            z-index: 9998;
            pointer-events: none;
            background: radial-gradient(circle at var(--sweep-x) var(--sweep-y),
                var(--sweep-color) 0%,
                var(--sweep-color) 35%,
                transparent 72%);
            clip-path: circle(0 at var(--sweep-x) var(--sweep-y));
            opacity: 1;
            transition: clip-path 0.8s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.8s ease;
        }

        .theme-sweep.run {
            clip-path: circle(150vmax at var(--sweep-x) var(--sweep-y));
            opacity: 0;
        }

        body.theme-transitioning,
        body.theme-transitioning *,
        body.theme-transitioning *::before,
        body.theme-transitioning *::after {
            transition: background-color 0.5s ease, color 0.5s ease,
                        border-color 0.5s ease, box-shadow 0.5s ease,
                        fill 0.5s ease, stroke 0.5s ease !important;
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        @media (max-width: 768px) {
            body { padding: 24px 16px 100px; }
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
            .hero-actions .btn-submit:last-child:nth-child(odd) {
                width: 100%;
            }
            .card-container { grid-template-columns: 1fr; }
            .epic-card { padding: 24px; }
            .theme-toggle {
                width: 50px;
                height: 50px;
                right: 16px;
                bottom: 16px;
            }
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

    <button type="button" class="theme-toggle" id="theme-toggle"
            aria-label="Aktifkan tema gelap" aria-pressed="false" title="Mode gelap">
        <span class="theme-icon theme-icon-moon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79z"></path>
            </svg>
        </span>
        <span class="theme-icon theme-icon-sun" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="4"></circle>
                <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"></path>
            </svg>
        </span>
    </button>

    <div class="header">
        <h1>Refleksi Mingguan</h1>
        <p>Level up perjalanan mentee dengan AI.</p>
        <div class="hero-actions">
            <a href="form.php" class="btn-submit">📝 Form Klasik</a>
            <a href="form_bebas.php" class="btn-submit btn-quaternary">🪶 Form Bebas</a>
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

    <script>
        (function () {
            var root = document.documentElement;
            var btn = document.getElementById('theme-toggle');
            var meta = document.querySelector('meta[name="theme-color"]');
            if (!btn) return;

            var reduceMotion = false;
            try {
                reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            } catch (e) {}

            function temaSaatIni() {
                return root.dataset.theme === 'dark' ? 'dark' : 'light';
            }

            function terapkanTema(theme) {
                root.dataset.theme = theme;

                try {
                    localStorage.setItem('refleksi-theme', theme);
                } catch (e) {}

                var gelap = theme === 'dark';
                btn.setAttribute('aria-pressed', gelap ? 'true' : 'false');
                btn.setAttribute('aria-label', gelap ? 'Aktifkan tema terang' : 'Aktifkan tema gelap');
                btn.title = gelap ? 'Mode terang' : 'Mode gelap';

                if (meta) {
                    meta.setAttribute('content', gelap ? '#05070f' : '#1d2f8a');
                }
            }

            // Samakan status tombol dengan tema yang sudah dipasang di <head>.
            terapkanTema(temaSaatIni());

            function ledakanTombol() {
                btn.classList.remove('burst');
                // Paksa reflow agar animasi bisa diputar ulang.
                void btn.offsetWidth;
                btn.classList.add('burst');
            }

            function transisiFallback(theme, cx, cy) {
                var overlay = document.createElement('div');
                overlay.className = 'theme-sweep';
                overlay.style.setProperty('--sweep-x', cx + 'px');
                overlay.style.setProperty('--sweep-y', cy + 'px');
                overlay.style.setProperty('--sweep-color', theme === 'dark' ? '#05070f' : '#1d2f8a');

                document.body.appendChild(overlay);
                document.body.classList.add('theme-transitioning');

                requestAnimationFrame(function () {
                    overlay.classList.add('run');
                });

                setTimeout(function () {
                    terapkanTema(theme);
                }, 240);

                setTimeout(function () {
                    overlay.remove();
                    document.body.classList.remove('theme-transitioning');
                }, 950);
            }

            btn.addEventListener('click', function () {
                var next = temaSaatIni() === 'dark' ? 'light' : 'dark';
                var rect = btn.getBoundingClientRect();
                var cx = rect.left + rect.width / 2;
                var cy = rect.top + rect.height / 2;

                ledakanTombol();

                if (reduceMotion) {
                    terapkanTema(next);
                    return;
                }

                if (typeof document.startViewTransition === 'function') {
                    var transition = document.startViewTransition(function () {
                        terapkanTema(next);
                    });

                    transition.ready.then(function () {
                        var radius = Math.hypot(
                            Math.max(cx, window.innerWidth - cx),
                            Math.max(cy, window.innerHeight - cy)
                        );

                        root.animate(
                            {
                                clipPath: [
                                    'circle(0px at ' + cx + 'px ' + cy + 'px)',
                                    'circle(' + radius + 'px at ' + cx + 'px ' + cy + 'px)'
                                ]
                            },
                            {
                                duration: 780,
                                easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                                pseudoElement: '::view-transition-new(root)'
                            }
                        );
                    }).catch(function () {});
                } else {
                    transisiFallback(next, cx, cy);
                }
            });
        })();
    </script>
</body>
</html>
