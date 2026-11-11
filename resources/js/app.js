import * as bootstrap from 'bootstrap';
import { initClockCtaColors } from './pages/clock-cta-color';
import { initDriverSort } from './pages/gh-driver-sort';
import { initGhOrderBoard } from './pages/gh-order-board';
import { initGhProductPickers } from './pages/gh-product-picker';
import { initTableFilters } from './pages/table-filter';
import { scrollChildIntoScrollerX } from './scroll-into-scroller';
import { initThemeToggle } from './theme';
import './pages/portal-clock';

window.bootstrap = bootstrap;

initTableFilters();
initDriverSort();
initGhOrderBoard();
initGhProductPickers();
initThemeToggle();
initClockCtaColors();

// Attendance / metric help: hover + focus + tap; attach to body to avoid table clipping.
const initHpTooltips = () => {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
        bootstrap.Tooltip.getOrCreateInstance(el, {
            container: 'body',
            trigger: 'hover focus click',
            placement: el.getAttribute('data-bs-placement') || 'auto',
            customClass: el.getAttribute('data-bs-custom-class') || 'hp-tooltip',
        });
    });
};
initHpTooltips();
document.addEventListener('shown.bs.modal', initHpTooltips);

// Flash toasts: Blade already renders `.show` so the message is visible before
// this deferred module loads. Calling toast.show() again would hide→show (flicker).
document.querySelectorAll('[data-hp-toast]').forEach((el) => {
    const delay = Number(el.getAttribute('data-bs-delay') || 5500);
    const autohide = el.getAttribute('data-bs-autohide') !== 'false';
    const toast = bootstrap.Toast.getOrCreateInstance(el, { autohide, delay });

    if (el.classList.contains('show')) {
        if (autohide) {
            window.setTimeout(() => toast.hide(), delay);
        }
        return;
    }

    toast.show();
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

// Keep the active global module tab visible inside its own overflow-x scroller.
// Do NOT use Element.scrollIntoView — it scrolls the page (Evaluation auto-jump).
const scrollActiveModuleTabIntoView = () => {
    const switcher = document.querySelector('[data-hp-module-switcher]');
    if (! switcher) {
        return;
    }

    const active = switcher.querySelector('.hp-module-tab--active, [aria-selected="true"]');
    if (! active) {
        return;
    }

    scrollChildIntoScrollerX(switcher, active, 'nearest');
};

scrollActiveModuleTabIntoView();
window.addEventListener('resize', () => {
    window.requestAnimationFrame(scrollActiveModuleTabIntoView);
});
