/**
 * Driver task lists — SortableJS (touch-first) with up/down fallback.
 *
 * V2.4.1: each [data-gh-sortable] area list owns its direct [data-sort-item]
 * children (no cross-area moves). Persistence posts the full board DOM order
 * under [data-gh-board-sort-root] so area-local drags still update the global
 * driver list sequence. Restores DOM if the save fails.
 */
import Sortable from 'sortablejs';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const boardRoot = (container) => container.closest('[data-gh-board-sort-root]') || container;

const items = (container) => Array.from(container.querySelectorAll(':scope > [data-sort-item]'));

const boardItems = (root) => Array.from(root.querySelectorAll('[data-sort-item]'));

const orderIds = (els) => els.map((el) => Number(el.dataset.orderId));

function renumber(container) {
    const list = items(container);
    list.forEach((el, index) => {
        const badge = el.querySelector('[data-sort-position]');
        if (badge) {
            badge.textContent = String(index + 1);
        }
        const up = el.querySelector('[data-sort-up]');
        const down = el.querySelector('[data-sort-down]');
        if (up) {
            up.disabled = index === 0;
        }
        if (down) {
            down.disabled = index === list.length - 1;
        }
    });
}

function showError(root) {
    const message = root.dataset.errorMessage || 'Could not save the new order.';
    root.querySelector(':scope > [data-sort-error]')?.remove();
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger py-2 small';
    alert.setAttribute('role', 'alert');
    alert.setAttribute('data-sort-error', '');
    alert.textContent = message;
    root.prepend(alert);
    window.setTimeout(() => alert.remove(), 5000);
}

function restoreBoard(root, ids) {
    const byId = new Map(boardItems(root).map((el) => [Number(el.dataset.orderId), el]));
    // Restore only relative order within each area list; items stay in their area.
    root.querySelectorAll('[data-gh-sortable]').forEach((list) => {
        const localIds = ids.filter((id) => {
            const el = byId.get(id);

            return el && list.contains(el);
        });
        localIds.forEach((id) => {
            const el = byId.get(id);
            if (el) {
                list.appendChild(el);
            }
        });
        renumber(list);
    });
}

async function flushSave(root) {
    if (root.dataset.saving === '1') {
        return;
    }
    const pending = root._pendingSave;
    if (! pending) {
        return;
    }
    root._pendingSave = null;
    root.dataset.saving = '1';
    const { previousIds, nextIds } = pending;
    const url = root.dataset.reorderUrl;
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                order_ids: nextIds,
                previous_order_ids: previousIds,
            }),
        });
        if (! response.ok) {
            throw new Error(String(response.status));
        }
        root.dataset.lastSavedOrder = nextIds.join(',');
    } catch (error) {
        restoreBoard(root, previousIds);
        showError(root);
        root._pendingSave = null;
    } finally {
        root.dataset.saving = '0';
        if (root._pendingSave) {
            flushSave(root);
        }
    }
}

function queueSave(root, previousIds) {
    const nextIds = orderIds(boardItems(root));
    if (previousIds.join(',') === nextIds.join(',')) {
        return;
    }
    const baseline = (root.dataset.lastSavedOrder || previousIds.join(','))
        .split(',')
        .filter(Boolean)
        .map(Number);
    root._pendingSave = { previousIds: baseline, nextIds };
    flushSave(root);
}

function commit(container, previousIds) {
    const root = boardRoot(container);
    renumber(container);
    queueSave(root, previousIds);
}

function move(container, item, direction) {
    const root = boardRoot(container);
    const previousIds = orderIds(boardItems(root));
    const sibling = direction < 0 ? item.previousElementSibling : item.nextElementSibling;
    if (! sibling || ! sibling.hasAttribute('data-sort-item')) {
        return;
    }
    if (direction < 0) {
        container.insertBefore(item, sibling);
    } else {
        container.insertBefore(sibling, item);
    }
    commit(container, previousIds);
}

export function initDriverSort() {
    document.querySelectorAll('[data-gh-sortable]').forEach((container) => {
        if (container.dataset.sortableReady === '1') {
            return;
        }
        container.dataset.sortableReady = '1';

        const root = boardRoot(container);
        if (! root.dataset.lastSavedOrder) {
            root.dataset.lastSavedOrder = orderIds(boardItems(root)).join(',');
        }
        renumber(container);

        if (items(container).length < 2) {
            container.querySelectorAll('[data-sort-up], [data-sort-down], [data-sort-handle]').forEach((el) => {
                if (el.matches('[data-sort-up], [data-sort-down]')) {
                    el.disabled = true;
                }
            });
        }

        Sortable.create(container, {
            animation: 180,
            handle: '[data-sort-handle]',
            draggable: '[data-sort-item]',
            ghostClass: 'hp-driver-sort-ghost',
            chosenClass: 'hp-driver-sort-chosen',
            dragClass: 'hp-driver-sort-drag',
            forceFallback: true,
            fallbackClass: 'hp-driver-sort-fallback',
            fallbackOnBody: true,
            swapThreshold: 0.65,
            scroll: true,
            scrollSensitivity: 60,
            scrollSpeed: 14,
            bubbleScroll: true,
            delay: 120,
            delayOnTouchOnly: true,
            touchStartThreshold: 4,
            preventOnFilter: true,
            // Keep drops inside this area list only (no cross-area).
            group: {
                name: `gh-area-${container.closest('[data-area-key]')?.getAttribute('data-area-key') || 'default'}`,
                pull: false,
                put: false,
            },
            onStart(evt) {
                const rootEl = boardRoot(container);
                rootEl.dataset.preOrder = orderIds(boardItems(rootEl)).join(',');
                container._didDrag = false;
                document.body.classList.add('hp-driver-sorting');
                evt.item.classList.add('hp-driver-sort-active');
            },
            onMove() {
                container._didDrag = true;
            },
            onEnd(evt) {
                document.body.classList.remove('hp-driver-sorting');
                evt.item.classList.remove('hp-driver-sort-active');
                const rootEl = boardRoot(container);
                const previousIds = (rootEl.dataset.preOrder || '')
                    .split(',')
                    .filter(Boolean)
                    .map(Number);
                delete rootEl.dataset.preOrder;
                if (container._didDrag || evt.oldIndex !== evt.newIndex) {
                    const suppress = (clickEvt) => {
                        clickEvt.preventDefault();
                        clickEvt.stopPropagation();
                    };
                    evt.item.addEventListener('click', suppress, { capture: true, once: true });
                    window.setTimeout(() => evt.item.removeEventListener('click', suppress, true), 400);
                }
                commit(container, previousIds);
            },
        });

        container.addEventListener('click', (event) => {
            const button = event.target.closest('[data-sort-up], [data-sort-down]');
            const item = button?.closest('[data-sort-item]');
            if (button && item && container.contains(item)) {
                event.preventDefault();
                move(container, item, button.hasAttribute('data-sort-up') ? -1 : 1);
            }
        });
    });
}
