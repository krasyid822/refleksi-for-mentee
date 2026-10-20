(function () {
    if (window.__REFLEKSI_AMBIENT_AUDIO_INIT__) {
        return;
    }
    window.__REFLEKSI_AMBIENT_AUDIO_INIT__ = true;

    const VIDEO_ID = 'clXNZw73AAE';
    const STORAGE_KEY = 'refleksi-ambient-audio-state';
    let player = null;
    let playerReady = false;
    let apiLoading = false;
    let pendingPlay = false;
    let pendingGesture = false;
    let blockedNotice = null;
    let autoplayTimer = null;
    let clockTimer = null;
    let gestureRetryHandler = null;
    let clockOffsetMs = 0;
    let clockTz = '';
    let clockOffsetLabel = '';

    const host = document.createElement('div');
    host.id = 'ambient-audio-widget';
    host.innerHTML = `
        <div class="ambient-audio-main">
            <button type="button" class="ambient-audio-toggle" aria-pressed="false">▶ Putar Audio</button>
            <div class="ambient-audio-marquee" aria-label="Judul audio berjalan">
                <div class="ambient-audio-track">
                    <span class="ambient-audio-text">Memuat judul audio...</span>
                    <span class="ambient-audio-text" aria-hidden="true">Memuat judul audio...</span>
                </div>
            </div>
        </div>
        <div class="clock ambient-audio-clock" id="ambient-audio-clock" aria-label="Waktu server">
            <span class="clock-time">--:--:--</span>
            <span class="clock-zone">memuat…</span>
        </div>
        <div class="ambient-audio-notice" role="status" aria-live="polite" hidden>
            <span>Audio otomatis diblokir browser.</span>
            <button type="button" class="ambient-audio-enable">Aktifkan audio</button>
        </div>
        <div id="ambient-audio-frame" class="ambient-audio-frame" aria-hidden="true"></div>
    `;

    const style = document.createElement('style');
    style.textContent = `
        #ambient-audio-widget {
            position: fixed;
            left: 18px;
            right: auto;
            bottom: 18px;
            z-index: 9990;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            padding: 9px 12px;
            border: 2px solid rgba(15, 23, 42, 0.14);
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: blur(10px);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.18);
            color: #0f172a;
            max-width: calc(100vw - 36px);
        }

        .ambient-audio-main {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .ambient-audio-toggle {
            border: 0;
            border-radius: 999px;
            padding: 10px 16px;
            background: linear-gradient(135deg, #0f172a, #1d4ed8);
            color: #ffffff;
            font: 600 0.95rem 'Space Grotesk', sans-serif;
            cursor: pointer;
            box-shadow: none;
            margin-top: 0;
            width: auto;
            min-width: 0;
            white-space: nowrap;
        }

        .ambient-audio-toggle:hover {
            transform: translateY(-1px);
        }

        .ambient-audio-toggle.needs-gesture {
            background: linear-gradient(135deg, #92400e, #f59e0b);
            color: #fff7ed;
            animation: ambient-audio-pulse 1.8s ease-in-out infinite;
        }

        @keyframes ambient-audio-pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.45); }
            50% { box-shadow: 0 0 0 8px rgba(245, 158, 11, 0); }
        }

        .ambient-audio-marquee {
            width: 190px;
            overflow: hidden;
            border-radius: 10px;
            border: 1px solid rgba(15, 23, 42, 0.2);
            background: rgba(255, 255, 255, 0.75);
            padding: 3px 0;
        }

        .ambient-audio-track {
            display: inline-flex;
            align-items: center;
            width: max-content;
            min-width: 100%;
            animation: ambient-marquee 13s steps(38, end) infinite;
        }

        .ambient-audio-text {
            flex: 0 0 auto;
            padding-right: 38px;
            font: 700 0.72rem 'Courier New', monospace;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #0f172a;
            text-shadow: 1px 1px 0 rgba(30, 64, 175, 0.25);
            white-space: nowrap;
        }

        @keyframes ambient-marquee {
            from { transform: translateX(0); }
            to { transform: translateX(-50%); }
        }

        /* div.clock di overlay audio */
        .ambient-audio-clock {
            display: flex;
            flex-direction: column;
            gap: 1px;
            padding: 0 2px 0 10px;
            border-left: 1px solid rgba(15, 23, 42, 0.18);
            font-variant-numeric: tabular-nums;
            line-height: 1.15;
            min-width: 74px;
        }

        .ambient-audio-clock .clock-time {
            font: 700 0.88rem 'Courier New', monospace;
            letter-spacing: 0.04em;
            color: #0f172a;
        }

        .ambient-audio-clock .clock-zone {
            font: 600 0.6rem 'Space Grotesk', sans-serif;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: rgba(15, 23, 42, 0.58);
            white-space: nowrap;
        }

        .ambient-audio-notice {
            position: absolute;
            left: 0;
            bottom: calc(100% + 8px);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            max-width: min(330px, calc(100vw - 30px));
            padding: 10px 12px;
            border-radius: 14px;
            border: 2px solid #f59e0b;
            background: #fffbeb;
            color: #78350f;
            font-size: 0.78rem;
            line-height: 1.35;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.18);
        }

        .ambient-audio-notice[hidden] { display: none; }

        .ambient-audio-notice button {
            border: 0;
            border-radius: 999px;
            padding: 6px 10px;
            background: #b45309;
            color: #fff7ed;
            font: 700 0.72rem 'Space Grotesk', sans-serif;
            cursor: pointer;
        }

        .ambient-audio-frame {
            position: fixed;
            left: -9999px;
            top: -9999px;
            width: 1px;
            height: 1px;
            overflow: hidden;
        }

        @media (max-width: 520px) {
            /* Layar sempit: sembunyikan marquee agar overlay tidak menutupi
               terlalu banyak konten; jam dan tombol tetap terlihat. */
            .ambient-audio-marquee { display: none; }
        }

        @media (max-width: 640px) {
            #ambient-audio-widget {
                left: 10px;
                right: auto;
                bottom: 12px;
                gap: 6px;
                padding: 6px 8px;
                border-radius: 14px;
                max-width: calc(100vw - 86px);
            }

            .ambient-audio-main { gap: 6px; }

            .ambient-audio-toggle {
                padding: 7px 10px;
                font-size: 0.76rem;
                line-height: 1.2;
            }

            .ambient-audio-marquee {
                width: 100px;
                padding: 2px 0;
            }

            .ambient-audio-text {
                font-size: 0.58rem;
                padding-right: 20px;
            }

            .ambient-audio-clock {
                min-width: 62px;
                padding-left: 7px;
            }

            .ambient-audio-clock .clock-time { font-size: 0.74rem; }
            .ambient-audio-clock .clock-zone { font-size: 0.52rem; }
        }
    `;

    document.head.appendChild(style);

    const button = host.querySelector('.ambient-audio-toggle');
    const notice = host.querySelector('.ambient-audio-notice');
    const enableButton = host.querySelector('.ambient-audio-enable');
    const clockTimeEl = host.querySelector('.ambient-audio-clock .clock-time');
    const clockZoneEl = host.querySelector('.ambient-audio-clock .clock-zone');
    blockedNotice = notice;

    function updateMarqueeTitle(rawTitle) {
        const title = (rawTitle || '').trim() || 'Audio YouTube';
        const decorated = 'Now Playing: ' + title + ' // ';
        host.querySelectorAll('.ambient-audio-text').forEach(function (node) {
            node.textContent = decorated;
        });
    }

    function setPlayingState(isPlaying) {
        button.textContent = isPlaying ? '⏸ Jeda Audio' : '▶ Putar Audio';
        button.setAttribute('aria-pressed', String(isPlaying));
        button.classList.remove('needs-gesture');
        try {
            localStorage.setItem(STORAGE_KEY, isPlaying ? 'playing' : 'paused');
        } catch (e) {}
    }

    function showBlockedNotice() {
        if (!notice) return;
        notice.hidden = false;
        button.textContent = '▶ Aktifkan Audio';
        button.classList.add('needs-gesture');
        armGestureRetry();
    }

    function hideBlockedNotice() {
        if (notice) notice.hidden = true;
        button.classList.remove('needs-gesture');
        disarmGestureRetry();
    }

    function disarmGestureRetry() {
        if (!gestureRetryHandler) return;
        document.removeEventListener('pointerdown', gestureRetryHandler, true);
        document.removeEventListener('keydown', gestureRetryHandler, true);
        gestureRetryHandler = null;
    }

    function armGestureRetry() {
        if (gestureRetryHandler) return;
        gestureRetryHandler = function () {
            disarmGestureRetry();
            if (localStorage.getItem(STORAGE_KEY) === 'playing') {
                playAudio(true);
            }
        };
        document.addEventListener('pointerdown', gestureRetryHandler, true);
        document.addEventListener('keydown', gestureRetryHandler, true);
    }

    function actualPlayingState() {
        if (!player || !playerReady || !window.YT) return false;
        try {
            const state = player.getPlayerState();
            return state === window.YT.PlayerState.PLAYING || state === window.YT.PlayerState.BUFFERING;
        } catch (e) {
            return false;
        }
    }

    function createPlayer() {
        if (player) {
            return player;
        }

        player = new window.YT.Player('ambient-audio-frame', {
            height: '1',
            width: '1',
            videoId: VIDEO_ID,
            playerVars: {
                autoplay: 0,
                controls: 0,
                rel: 0,
                modestbranding: 1,
                loop: 1,
                playlist: VIDEO_ID,
                playsinline: 1,
                iv_load_policy: 3,
                fs: 0,
                disablekb: 1
            },
            events: {
                onReady: function () {
                    playerReady = true;
                    const videoData = player.getVideoData ? player.getVideoData() : null;
                    updateMarqueeTitle(videoData && videoData.title ? videoData.title : '');
                    const savedState = localStorage.getItem(STORAGE_KEY) || 'paused';
                    if (pendingPlay || savedState === 'playing') {
                        const gesture = pendingGesture;
                        pendingPlay = false;
                        pendingGesture = false;
                        playAudio(gesture);
                    }
                },
                onStateChange: function (event) {
                    const videoData = player.getVideoData ? player.getVideoData() : null;
                    if (videoData && videoData.title) {
                        updateMarqueeTitle(videoData.title);
                    }
                    if (event.data === window.YT.PlayerState.PLAYING) {
                        clearTimeout(autoplayTimer);
                        hideBlockedNotice();
                        setPlayingState(true);
                    } else if (event.data === window.YT.PlayerState.PAUSED || event.data === window.YT.PlayerState.ENDED) {
                        clearTimeout(autoplayTimer);
                        setPlayingState(false);
                    }
                },
                onError: function () {
                    showBlockedNotice();
                    if (notice) {
                        notice.querySelector('span').textContent = 'Audio gagal diputar. Periksa koneksi atau klik untuk mencoba lagi.';
                    }
                }
            }
        });

        return player;
    }

    function loadApi() {
        if (window.YT && window.YT.Player) {
            createPlayer();
            return;
        }

        if (apiLoading) {
            return;
        }

        apiLoading = true;
        const existing = document.querySelector('script[data-ambient-audio-api]');
        if (existing) {
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://www.youtube.com/iframe_api';
        script.async = true;
        script.dataset.ambientAudioApi = 'true';
        script.onerror = function () {
            if (notice) {
                notice.hidden = false;
                notice.querySelector('span').textContent = 'Pemutar audio gagal dimuat. Periksa koneksi internet.';
            }
        };
        document.head.appendChild(script);

        window.onYouTubeIframeAPIReady = function () {
            createPlayer();
        };
    }

    function playAudio(fromUserGesture) {
        loadApi();
        if (!window.YT || !window.YT.Player) {
            pendingPlay = true;
            pendingGesture = Boolean(fromUserGesture);
            return;
        }

        if (!player) {
            createPlayer();
        }

        if (!playerReady) {
            pendingPlay = true;
            pendingGesture = Boolean(fromUserGesture);
            return;
        }

        try {
            clearTimeout(autoplayTimer);
            player.playVideo();
            setPlayingState(true);
            hideBlockedNotice();

            // playVideo() tidak mengembalikan Promise. Jika autoplay diblokir,
            // state tidak berubah menjadi PLAYING; tampilkan pemicu ke pengguna.
            if (!fromUserGesture) {
                autoplayTimer = setTimeout(function () {
                    if (!actualPlayingState()) {
                        showBlockedNotice();
                    }
                }, 1800);
            }
        } catch (error) {
            pendingPlay = true;
            pendingGesture = true;
            showBlockedNotice();
        }
    }

    function pauseAudio() {
        clearTimeout(autoplayTimer);
        if (player && playerReady) {
            try {
                player.pauseVideo();
            } catch (error) {
                // ignore
            }
        }
        hideBlockedNotice();
        setPlayingState(false);
    }

    button.addEventListener('click', function () {
        if (actualPlayingState()) {
            pauseAudio();
        } else {
            hideBlockedNotice();
            playAudio(true);
        }
    });

    enableButton.addEventListener('click', function () {
        hideBlockedNotice();
        playAudio(true);
    });

    // ------------------------------------------------------------------
    // Jam server di overlay audio
    // ------------------------------------------------------------------
    function pad2(value) {
        return String(value).padStart(2, '0');
    }

    function renderClock() {
        if (!clockTimeEl) return;
        const now = new Date(Date.now() + clockOffsetMs);
        let timeText;
        if (clockTz) {
            try {
                timeText = new Intl.DateTimeFormat('id-ID', {
                    timeZone: clockTz,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false
                }).format(now).replace(/\./g, ':');
            } catch (e) {
                timeText = pad2(now.getHours()) + ':' + pad2(now.getMinutes()) + ':' + pad2(now.getSeconds());
            }
        } else {
            timeText = pad2(now.getHours()) + ':' + pad2(now.getMinutes()) + ':' + pad2(now.getSeconds());
        }
        clockTimeEl.textContent = timeText;
        if (clockZoneEl) {
            clockZoneEl.textContent = clockOffsetLabel || (clockTz ? clockTz.split('/').pop().replace('_', ' ') : 'waktu lokal');
        }
    }

    async function syncClock() {
        try {
            const response = await fetch('time.php', { cache: 'no-store', headers: { 'Accept': 'application/json' } });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();
            const php = data && data.php ? data.php : null;
            if (php && Number.isFinite(Number(php.epoch_ms))) {
                clockOffsetMs = Number(php.epoch_ms) - Date.now();
                clockTz = typeof php.tz === 'string' ? php.tz : '';
                clockOffsetLabel = typeof php.offset_label === 'string' ? php.offset_label : '';
            }
        } catch (error) {
            clockOffsetMs = 0;
            try {
                clockTz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
            } catch (e) {
                clockTz = '';
            }
            clockOffsetLabel = 'lokal';
        }
        renderClock();
    }

    function startClock() {
        renderClock();
        syncClock();
        clockTimer = setInterval(renderClock, 1000);
        setInterval(syncClock, 60000);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) syncClock();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.body.appendChild(host);
        startClock();

        const savedState = localStorage.getItem(STORAGE_KEY) || 'paused';
        setPlayingState(savedState === 'playing');
        if (savedState === 'playing') {
            playAudio(false);
        }
    });
})();
