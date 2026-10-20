/* ============================================================
   page-transition.js
   - Menampilkan splash loading hanya saat navigasi terasa lambat.
   - Status berubah mengikuti proses yang sedang berjalan.
   - Tetap aman untuk browser tanpa View Transitions API.
   ============================================================ */
(function () {
    'use strict';

    var PENDING_KEY = 'refleksi-vt-pending-v1';
    var SHOW_DELAY_MS = 320;
    var ARRIVAL_CHECK_MS = 350;
    var MIN_VISIBLE_MS = 480;
    var PENDING_TTL_MS = 120000;
    var reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

    var overlay = null;
    var titleEl = null;
    var statusEl = null;
    var showTimer = null;
    var hideTimer = null;
    var arrivalTimer = null;
    var safetyTimer = null;
    var phaseTimers = [];
    var hideToken = 0;
    var active = false;
    var shownAt = 0;

    var DEFAULT_PHASES = [
        { at: 900, text: 'Menghubungi server...' },
        { at: 2200, text: 'Memuat data dan menyusun tampilan...' },
        { at: 5000, text: 'Masih bekerja. Koneksi mungkin sedang lambat...' },
        { at: 9000, text: 'Hampir selesai, terima kasih sudah menunggu...' }
    ];

    var CONFIGS = {
        'open-classic': {
            title: 'Membuka Form Klasik',
            status: 'Menyiapkan formulir dan pilihan check-in...',
            phases: [
                { at: 900, text: 'Memuat pertanyaan refleksi...' },
                { at: 2200, text: 'Menyiapkan komponen formulir...' },
                { at: 5000, text: 'Masih memuat. Koneksi mungkin sedang lambat...' }
            ]
        },
        'open-bebas': {
            title: 'Membuka Form Bebas',
            status: 'Menyiapkan form satu kolom...',
            phases: [
                { at: 900, text: 'Memuat pemicu refleksi...' },
                { at: 2200, text: 'Menyiapkan form dan panduan AI...' },
                { at: 5000, text: 'Masih memuat. Koneksi mungkin sedang lambat...' }
            ]
        },
        'open-detail': {
            title: 'Membuka detail refleksi',
            status: 'Mengambil data refleksi dari server...',
            phases: [
                { at: 900, text: 'Menyusun ringkasan refleksi...' },
                { at: 2200, text: 'Memuat kalimat epik dan status privasi...' },
                { at: 5000, text: 'Masih memuat. Koneksi mungkin sedang lambat...' }
            ]
        },
        'open-dashboard': {
            title: 'Kembali ke dashboard',
            status: 'Memuat daftar refleksi...',
            phases: [
                { at: 900, text: 'Mengambil data refleksi terbaru...' },
                { at: 2200, text: 'Menyusun kartu dan ringkasan...' },
                { at: 5000, text: 'Masih memuat. Koneksi mungkin sedang lambat...' }
            ]
        },
        'form-classic': {
            title: 'Menyimpan refleksi',
            status: 'Mengirim jawabanmu ke server...',
            phases: [
                { at: 1200, text: 'AI sedang menyusun kalimat epik dari refleksimu...' },
                { at: 4000, text: 'Model AI masih memproses. Biasanya perlu beberapa detik...' },
                { at: 9000, text: 'Jika model pertama sibuk, sistem mencoba model gratis lain...' },
                { at: 16000, text: 'Prosesnya lebih lama dari biasanya, mohon tetap di halaman ini...' }
            ]
        },
        'form-bebas': {
            title: 'Memproses ceritamu',
            status: 'AI sedang membaca dan memecah ceritamu...',
            phases: [
                { at: 1200, text: 'AI sedang memilih skema yang paling cocok...' },
                { at: 4000, text: 'AI sedang memetakan cerita ke kolom-kolom database...' },
                { at: 9000, text: 'Jika model pertama sibuk, sistem mencoba model gratis lain...' },
                { at: 16000, text: 'Prosesnya lebih lama dari biasanya, mohon tetap di halaman ini...' }
            ]
        },
        'search': {
            title: 'Mencari refleksi',
            status: 'Memfilter daftar refleksi...',
            phases: [
                { at: 900, text: 'Mencocokkan kata kunci dengan data refleksi...' },
                { at: 2200, text: 'Menyusun hasil pencarian...' }
            ]
        },
        'detail-password': {
            title: 'Memeriksa sandi',
            status: 'Membuka nama yang disamarkan...',
            phases: [
                { at: 900, text: 'Memverifikasi sandi...' },
                { at: 2200, text: 'Menyiapkan detail refleksi...' }
            ]
        },
        'regenerate': {
            title: 'Membuat ulang kalimat epik',
            status: 'Menghubungi AI...',
            phases: [
                { at: 1200, text: 'AI sedang menulis versi baru kalimat epik...' },
                { at: 4000, text: 'Model AI masih memproses...' },
                { at: 9000, text: 'Jika model pertama sibuk, sistem mencoba model gratis lain...' },
                { at: 16000, text: 'Prosesnya lebih lama dari biasanya, mohon tetap di halaman ini...' }
            ]
        },
        'delete': {
            title: 'Menghapus refleksi',
            status: 'Menghapus data dari database...',
            phases: [
                { at: 900, text: 'Memeriksa data yang akan dihapus...' },
                { at: 2200, text: 'Menyelesaikan penghapusan...' }
            ]
        },
        'privacy': {
            title: 'Mengubah status privasi',
            status: 'Menyimpan pengaturan privasi...',
            phases: [
                { at: 900, text: 'Memperbarui status nama...' },
                { at: 2200, text: 'Menyiapkan halaman detail...' }
            ]
        },
        'form': {
            title: 'Menyimpan data',
            status: 'Mengirim data ke server...',
            phases: DEFAULT_PHASES
        },
        'link': {
            title: 'Membuka halaman',
            status: 'Menghubungi server...',
            phases: DEFAULT_PHASES
        }
    };

    function safeStorageGet() {
        try {
            return window.sessionStorage.getItem(PENDING_KEY);
        } catch (e) {
            return null;
        }
    }

    function safeStorageSet(value) {
        try {
            window.sessionStorage.setItem(PENDING_KEY, value);
        } catch (e) {
            /* sessionStorage bisa diblokir; splash tetap berfungsi di halaman ini. */
        }
    }

    function safeStorageRemove() {
        try {
            window.sessionStorage.removeItem(PENDING_KEY);
        } catch (e) {
            /* abaikan */
        }
    }

    function persistPending(action, label) {
        safeStorageSet(JSON.stringify({
            ts: Date.now(),
            action: action || 'link',
            label: label || ''
        }));
    }

    function readPending() {
        var raw = safeStorageGet();
        if (!raw) return null;
        try {
            var data = JSON.parse(raw);
            if (!data || typeof data.ts !== 'number') {
                safeStorageRemove();
                return null;
            }
            if (Date.now() - data.ts > PENDING_TTL_MS) {
                safeStorageRemove();
                return null;
            }
            return data;
        } catch (e) {
            safeStorageRemove();
            return null;
        }
    }

    function configFor(action, label) {
        var config = CONFIGS[action] || CONFIGS.link;
        var title = config.title;
        if (action === 'link' && label) {
            title = 'Membuka ' + label;
        }
        return {
            title: title,
            status: config.status,
            phases: config.phases || DEFAULT_PHASES
        };
    }

    function createOverlay() {
        if (overlay) return;
        overlay = document.createElement('div');
        overlay.className = 'vt-splash';
        overlay.id = 'vt-splash';
        overlay.hidden = true;
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML =
            '<div class="vt-splash-card" role="status" aria-live="polite" aria-atomic="true">' +
                '<div class="vt-splash-eyebrow">Refleksi Mingguan</div>' +
                '<div class="vt-splash-spinner" aria-hidden="true"></div>' +
                '<h2 class="vt-splash-title" id="vt-splash-title">Menyiapkan halaman...</h2>' +
                '<p class="vt-splash-status" id="vt-splash-status">Menghubungi server...</p>' +
                '<div class="vt-splash-progress" aria-hidden="true"><span></span></div>' +
                '<p class="vt-splash-hint">Tekan <kbd>Esc</kbd> untuk menutup tampilan ini</p>' +
            '</div>';
        document.body.appendChild(overlay);
        titleEl = overlay.querySelector('#vt-splash-title');
        statusEl = overlay.querySelector('#vt-splash-status');
    }

    function clearPhaseTimers() {
        for (var i = 0; i < phaseTimers.length; i++) {
            clearTimeout(phaseTimers[i]);
        }
        phaseTimers = [];
    }

    function startPhases(phases) {
        clearPhaseTimers();
        var list = phases && phases.length ? phases : DEFAULT_PHASES;
        list.forEach(function (phase) {
            phaseTimers.push(setTimeout(function () {
                if (active && statusEl && phase.text) {
                    statusEl.textContent = phase.text;
                }
            }, phase.at));
        });
    }

    function setContent(config) {
        if (!titleEl || !statusEl) return;
        titleEl.textContent = config.title || 'Menyiapkan halaman...';
        statusEl.textContent = config.status || 'Menghubungi server...';
    }

    function showSplash(config) {
        createOverlay();
        clearTimeout(showTimer);
        showTimer = null;
        clearTimeout(hideTimer);
        hideToken++;

        if (active) {
            setContent(config);
            startPhases(config.phases);
            return;
        }

        active = true;
        shownAt = Date.now();
        setContent(config);
        startPhases(config.phases);

        overlay.hidden = false;
        overlay.setAttribute('aria-hidden', 'false');
        /* Paksa reflow supaya transisi opacity berjalan. */
        void overlay.offsetWidth;
        overlay.classList.add('is-active');
        document.body.setAttribute('aria-busy', 'true');

        clearTimeout(safetyTimer);
        safetyTimer = setTimeout(function () {
            hideSplash(true);
        }, 120000);
    }

    function scheduleSplash(config, delay) {
        clearTimeout(showTimer);
        showTimer = setTimeout(function () {
            showSplash(config);
        }, typeof delay === 'number' ? delay : SHOW_DELAY_MS);
    }

    function clearPending() {
        safeStorageRemove();
    }

    function hideSplash(immediate) {
        clearTimeout(showTimer);
        showTimer = null;
        clearTimeout(arrivalTimer);
        arrivalTimer = null;
        clearTimeout(safetyTimer);
        safetyTimer = null;

        if (!active) {
            clearPending();
            return;
        }

        var wait = 0;
        if (!immediate && !reduceMotion) {
            wait = Math.max(0, MIN_VISIBLE_MS - (Date.now() - shownAt));
        }

        var token = ++hideToken;
        active = false;
        clearPhaseTimers();

        hideTimer = setTimeout(function () {
            if (token !== hideToken || !overlay) return;
            overlay.classList.remove('is-active');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.removeAttribute('aria-busy');
            clearPending();

            if (reduceMotion) {
                overlay.hidden = true;
                return;
            }

            setTimeout(function () {
                if (token === hideToken && overlay) {
                    overlay.hidden = true;
                }
            }, 260);
        }, wait);
    }

    function cleanLabel(text) {
        return String(text || '').replace(/\s+/g, ' ').trim().slice(0, 70);
    }

    function actionForUrl(url) {
        var path = url.pathname;
        var file = path.substring(path.lastIndexOf('/') + 1);
        if (file === 'form.php') return 'open-classic';
        if (file === 'form_bebas.php') return 'open-bebas';
        if (file === 'detail.php') return 'open-detail';
        if (file === 'index.php' || file === '') return 'open-dashboard';
        if (file === 'regenerate_epic.php') return 'regenerate';
        if (file === 'delete.php') return 'delete';
        if (file === 'toggle_privacy.php') return 'privacy';
        return 'link';
    }

    function isSameOrigin(url) {
        return (url.protocol === 'http:' || url.protocol === 'https:') && url.origin === window.location.origin;
    }

    function isHashOnly(url) {
        return !!url.hash
            && url.pathname === window.location.pathname
            && url.search === window.location.search;
    }

    function handleLinkClick(event) {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        var link = event.target.closest ? event.target.closest('a[href]') : null;
        if (!link) return;
        if (link.hasAttribute('download') || link.hasAttribute('data-no-splash')) return;
        if (link.target && link.target !== '_self') return;

        var url;
        try {
            url = new URL(link.href, window.location.href);
        } catch (e) {
            return;
        }

        if (!isSameOrigin(url) || isHashOnly(url)) return;
        if (/\/download\.php$/i.test(url.pathname)) return;

        var label = cleanLabel(link.getAttribute('data-loading-label') || link.textContent);
        var action = actionForUrl(url);
        persistPending(action, label);
        scheduleSplash(configFor(action, label), SHOW_DELAY_MS);
    }

    function actionForForm(form, url) {
        if (form.id === 'form-bebas') return 'form-bebas';
        if (form.classList && form.classList.contains('search-form')) return 'search';
        if (form.classList && form.classList.contains('password-form')) return 'detail-password';
        if (/\/form\.php$/i.test(url.pathname)) return 'form-classic';
        return 'form';
    }

    function handleFormSubmit(event) {
        var form = event.target;
        if (!form || form.tagName !== 'FORM') return;
        if (form.hasAttribute('data-no-splash')) return;
        if (form.target && form.target !== '_self') return;

        var action = form.getAttribute('action') || window.location.href;
        var url;
        try {
            url = new URL(action, window.location.href);
        } catch (e) {
            return;
        }
        if (!isSameOrigin(url)) return;

        var kind = actionForForm(form, url);
        persistPending(kind, '');
        scheduleSplash(configFor(kind, ''), SHOW_DELAY_MS);
    }

    function handleArrival() {
        var pending = readPending();
        if (!pending) return;

        var config = configFor(pending.action, pending.label);
        var elapsed = Date.now() - pending.ts;

        // Navigasi sudah lama: lanjutkan splash di halaman baru.
        if (elapsed > SHOW_DELAY_MS) {
            scheduleSplash(config, 60);
            return;
        }

        // Navigasi cepat: tampilkan hanya jika halaman belum selesai dimuat.
        arrivalTimer = setTimeout(function () {
            arrivalTimer = null;
            if (document.readyState !== 'complete') {
                scheduleSplash(config, 0);
            } else {
                clearPending();
            }
        }, ARRIVAL_CHECK_MS);
    }

    function init() {
        createOverlay();
        handleArrival();

        document.addEventListener('click', handleLinkClick, false);
        document.addEventListener('submit', handleFormSubmit, false);

        window.addEventListener('load', function () {
            hideSplash(false);
        });

        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                hideSplash(true);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && active) {
                hideSplash(true);
            }
        });

        // Jika skrip baru aktif setelah load selesai, jangan biarkan splash menggantung.
        if (document.readyState === 'complete') {
            hideSplash(false);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
