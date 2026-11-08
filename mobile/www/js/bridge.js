/**
 * Offline / misconfig fallback only.
 * When server.url is set, Capacitor loads the remote Laravel host and injects the native bridge.
 */
(function () {
  var hint = document.getElementById('hint');
  var native = typeof window !== 'undefined'
    && window.Capacitor
    && typeof window.Capacitor.isNativePlatform === 'function'
    && window.Capacitor.isNativePlatform();

  if (!native) {
    if (hint) {
      hint.textContent = 'Open the TEST or LIVE Android APK to connect securely.';
    }
    return;
  }

  if (hint) {
    hint.textContent = 'If this screen persists, apply the Capacitor environment config and rebuild.';
  }
})();
