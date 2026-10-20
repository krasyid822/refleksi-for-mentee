(function registerPWA() {
  if (!('serviceWorker' in navigator)) {
    console.info('Service worker tidak tersedia di browser ini.');
    return;
  }

  const BUILD_KEY = 'refleksi-build';
  let registration = null;
  let refreshing = false;
  let hadController = !!navigator.serviceWorker.controller;

  navigator.serviceWorker.addEventListener('controllerchange', () => {
    // Instalasi pertama tidak perlu reload. Update build berikutnya perlu
    // reload agar halaman memakai aset dari cache baru.
    if (!hadController) {
      hadController = true;
      return;
    }
    if (refreshing) {
      return;
    }
    refreshing = true;
    window.location.reload();
  });

  async function fetchBuildInfo() {
    try {
      const response = await fetch('build.json', { cache: 'no-store' });
      if (!response.ok) return null;
      return await response.json();
    } catch (error) {
      return null;
    }
  }

  async function checkBuild() {
    const info = await fetchBuildInfo();
    if (!info || !info.build_id) return;

    const known = localStorage.getItem(BUILD_KEY);
    if (known && known !== info.build_id && registration) {
      console.info('Build baru terdeteksi:', info.build_id);
      try {
        await registration.update();
      } catch (error) {
        console.warn('Gagal memeriksa update service worker:', error);
      }
    }
    localStorage.setItem(BUILD_KEY, info.build_id);
  }

  const register = () => {
    navigator.serviceWorker.register('service-worker.js', {
      scope: './',
      updateViaCache: 'none'
    })
      .then((reg) => {
        registration = reg;
        console.info('Service worker terdaftar.');
        reg.update().catch((err) => {
          console.warn('Gagal memaksa update service worker:', err);
        });

        reg.addEventListener('updatefound', () => {
          console.info('Service worker baru terdeteksi.');
        });

        checkBuild();
      })
      .catch(err => console.error('Gagal mendaftarkan service worker:', err));
  };

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    register();
  } else {
    window.addEventListener('DOMContentLoaded', register, { once: true });
  }

  // Cek build baru secara berkala dan saat tab kembali aktif/online.
  setInterval(checkBuild, 5 * 60 * 1000);
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) checkBuild();
  });
  window.addEventListener('online', checkBuild);
})();
