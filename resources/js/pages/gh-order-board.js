/**
 * GH Order Board — active stage tab horizontal scroll + mobile filter toggle.
 */
import { scrollChildIntoScrollerX } from '../scroll-into-scroller';

export function initGhOrderBoard() {
    const scrollActiveTab = () => {
        document.querySelectorAll('.gh-board-tabs-scroll').forEach((scroller) => {
            const active = scroller.querySelector('.gh-board-tab.is-active, [aria-current="page"]');
            if (! active) {
                return;
            }

            // Scroller-only — never scroll the document/window.
            scrollChildIntoScrollerX(scroller, active, 'center');
        });
    };

    scrollActiveTab();
    window.addEventListener('load', scrollActiveTab, { once: true });

    document.querySelectorAll('[data-gh-board-filters]').forEach((form) => {
        const toggle = form.querySelector('[data-gh-filters-toggle]');
        const secondary = form.querySelector('[data-gh-filters-secondary]');
        if (! toggle || ! secondary) {
            return;
        }

        toggle.addEventListener('click', () => {
            const open = ! secondary.classList.contains('is-open');
            secondary.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });
}
