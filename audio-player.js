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

    const host = document.createElement('div');
    host.id = 'ambient-audio-widget';
    host.innerHTML = `
        <button type="button" class="ambient-audio-toggle" aria-pressed="false">▶ Putar Audio</button>
        <div class="ambient-audio-marquee" aria-label="Judul audio berjalan">
            <div class="ambient-audio-track">
                <span class="ambient-audio-text">Memuat judul audio...</span>
                <span class="ambient-audio-text" aria-hidden="true">Memuat judul audio...</span>
            </div>
        </div>
        <div id="ambient-audio-frame" class="ambient-audio-frame" aria-hidden="true"></div>
    `;

    const style = document.createElement('style');
    style.textContent = `
        #ambient-audio-widget {
            position: fixed;
            right: 18px;
            bottom: 18px;
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border: 2px solid rgba(15, 23, 42, 0.14);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(10px);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.18);
            color: #0f172a;
            max-width: calc(100vw - 24px);
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
        }

        .ambient-audio-toggle:hover {
            transform: translateY(-1px);
        }

        .ambient-audio-marquee {
            width: 220px;
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
            font: 700 0.76rem 'Courier New', monospace;
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

        .ambient-audio-frame {
            position: fixed;
            left: -9999px;
            top: -9999px;
            width: 1px;
            height: 1px;
            overflow: hidden;
        }

        @media (max-width: 640px) {
            #ambient-audio-widget {
                left: auto;
                right: 10px;
                bottom: 12px;
                justify-content: flex-end;
                gap: 6px;
                padding: 6px 8px;
                max-width: 220px;
            }

            .ambient-audio-toggle {
                padding: 7px 10px;
                font-size: 0.78rem;
                line-height: 1.2;
            }

            .ambient-audio-marquee {
                width: 112px;
                padding: 2px 0;
            }

            .ambient-audio-text {
                font-size: 0.62rem;
                padding-right: 22px;
            }
        }
    `;

    document.head.appendChild(style);

    function updateMarqueeTitle(rawTitle) {
        const title = (rawTitle || '').trim() || 'Audio YouTube';
        const decorated = 'Now Playing: ' + title + ' // ';
        const textNodes = host.querySelectorAll('.ambient-audio-text');
        textNodes.forEach(function (node) {
            node.textContent = decorated;
        });
    }

    function setPlayingState(isPlaying) {
        const button = host.querySelector('.ambient-audio-toggle');
        button.textContent = isPlaying ? '⏸ Jeda Audio' : '▶ Putar Audio';
        button.setAttribute('aria-pressed', String(isPlaying));
        localStorage.setItem(STORAGE_KEY, isPlaying ? 'playing' : 'paused');
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
                        pendingPlay = false;
                        playAudio();
                    }
                },
                onStateChange: function (event) {
                    const videoData = player.getVideoData ? player.getVideoData() : null;
                    if (videoData && videoData.title) {
                        updateMarqueeTitle(videoData.title);
                    }
                    if (event.data === window.YT.PlayerState.PLAYING) {
                        setPlayingState(true);
                    } else if (event.data === window.YT.PlayerState.PAUSED || event.data === window.YT.PlayerState.ENDED) {
                        setPlayingState(false);
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
        document.head.appendChild(script);

        window.onYouTubeIframeAPIReady = function () {
            createPlayer();
        };
    }

    function playAudio() {
        loadApi();
        if (!window.YT || !window.YT.Player) {
            pendingPlay = true;
            return;
        }

        if (!player) {
            createPlayer();
        }

        if (playerReady) {
            try {
                player.playVideo();
                setPlayingState(true);
            } catch (error) {
                pendingPlay = true;
            }
        } else {
            pendingPlay = true;
        }
    }

    function pauseAudio() {
        if (player && playerReady) {
            try {
                player.pauseVideo();
            } catch (error) {
                // ignore
            }
        }
        setPlayingState(false);
    }

    const button = host.querySelector('.ambient-audio-toggle');
    button.addEventListener('click', function () {
        const isPlaying = localStorage.getItem(STORAGE_KEY) === 'playing';
        if (isPlaying) {
            pauseAudio();
        } else {
            playAudio();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.body.appendChild(host);
        const savedState = localStorage.getItem(STORAGE_KEY) || 'paused';
        setPlayingState(savedState === 'playing');
        if (savedState === 'playing') {
            playAudio();
        }
    });
})();
