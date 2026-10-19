(function registerPWA() {
  if (!('serviceWorker' in navigator)) {
    console.info('Service worker tidak tersedia di browser ini.');
    return;
  }

  const register = () => {
    navigator.serviceWorker.register('service-worker.js', { updateViaCache: 'none' })
      .then((registration) => {
        console.info('Service worker terdaftar.');
        registration.update().catch((err) => {
          console.warn('Gagal memaksa update service worker:', err);
        });

        registration.addEventListener('updatefound', () => {
          console.info('Service worker baru terdeteksi.');
        });
      })
      .catch(err => console.error('Gagal mendaftarkan service worker:', err));
  };

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    register();
  } else {
    window.addEventListener('DOMContentLoaded', register, { once: true });
  }
})();
