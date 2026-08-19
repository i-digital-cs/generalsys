function initPortalClocks() {
    document.querySelectorAll('[data-portal-clock]').forEach((root) => {
        const timeEl = root.querySelector('[data-portal-clock-time]');
        const timeZone = root.getAttribute('data-timezone') || 'Africa/Cairo';
        const locale = root.getAttribute('data-locale') || document.documentElement.lang || 'ar';

        if (! timeEl) {
            return;
        }

        const tick = () => {
            timeEl.textContent = new Intl.DateTimeFormat(locale, {
                timeZone,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false,
            }).format(new Date());
        };

        tick();
        window.setInterval(tick, 1000);
    });
}

initPortalClocks();
