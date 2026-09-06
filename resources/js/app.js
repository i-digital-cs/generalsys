import * as bootstrap from 'bootstrap';
import { initClockCtaColors } from './pages/clock-cta-color';
import { initTableFilters } from './pages/table-filter';
import { initThemeToggle } from './theme';
import './pages/portal-clock';

window.bootstrap = bootstrap;

initTableFilters();
initThemeToggle();
initClockCtaColors();

// Flash toasts: Blade renders them with .show (visible before this module runs).
document.querySelectorAll('[data-hp-toast]').forEach((el) => {
    bootstrap.Toast.getOrCreateInstance(el).show();
});

// Navbar user menu: Popper's bottom-end hugs the wrong edge under dir=rtl,
// so pin placement from the document direction once at boot.
document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach((toggle) => {
    if (! toggle.closest('.navbar')) {
        return;
    }

    const placement = document.documentElement.dir === 'rtl' ? 'bottom-start' : 'bottom-end';

    bootstrap.Dropdown.getOrCreateInstance(toggle, {
        popperConfig: {
            strategy: 'fixed',
            placement,
            modifiers: [
                { name: 'offset', options: { offset: [0, 8] } },
                { name: 'preventOverflow', options: { boundary: 'viewport', padding: 8 } },
            ],
        },
    });
});
