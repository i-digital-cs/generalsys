/**
 * Driver task lists — SortableJS (touch-first) with up/down fallback.
 * Persists only on drop / button move; restores DOM if the save fails.
 * Queues the latest order if a save is already in flight.
 */
import Sortable from 'sortablejs';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const items = (container) => Array.from(container.querySelectorAll(':scope > [data-sort-item]'));

const orderIds = (container) => items(container).map((el) => Number(el.dataset.orderId));

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

function showError(container) {
    const message = container.dataset.errorMessage || 'Could not save the new order.';
    container.querySelector(':scope > [data-sort-error]')?.remove();
    const alert = document.createElement('div');
    alert.className = 'alert alert-danger py-2 small';
    alert.setAttribute('role', 'alert');
    alert.setAttribute('data-sort-error', '');
    alert.textContent = message;
    container.prepend(alert);
    window.setTimeout(() => alert.remove(), 5000);
}

function restore(container, ids) {
    const byId = new Map(items(container).map((el) => [Number(el.dataset.orderId), el]));
    ids.forEach((id) => {
        const el = byId.get(id);
        if (el) {
            container.appendChild(el);
        }
    });
    renumber(container);
}

async function flushSave(container) {
    if (container.dataset.saving === '1') {
        return;
    }
    const pending = container._pendingSave;
    if (! pending) {
        return;
    }
    container._pendingSave = null;
    container.dataset.saving = '1';
    const { previousIds, nextIds } = pending;
    try {
        const response = await fetch(container.dataset.reorderUrl, {
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
        container.dataset.lastSavedOrder = nextIds.join(',');
    } catch (error) {
        restore(container, previousIds);
        showError(container);
        container._pendingSave = null;
    } finally {
        container.dataset.saving = '0';
        if (container._pendingSave) {
            flushSave(container);
        }
    }
}

function queueSave(container, previousIds) {
    const nextIds = orderIds(container);
    if (previousIds.join(',') === nextIds.join(',')) {
        return;
    }
    // Baseline for optimistic concurrency: last known server order, else gesture start.
    const baseline = (container.dataset.lastSavedOrder || previousIds.join(','))
        .split(',')
        .filter(Boolean)
        .map(Number);
    container._pendingSave = { previousIds: baseline, nextIds };
    flushSave(container);
}

function commit(container, previousIds) {
    renumber(container);
    queueSave(container, previousIds);
}

function move(container, item, direction) {
    const previousIds = orderIds(container);
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
        container.dataset.lastSavedOrder = orderIds(container).join(',');
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
            onStart(evt) {
                container.dataset.preOrder = orderIds(container).join(',');
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
                const previousIds = (container.dataset.preOrder || '')
                    .split(',')
                    .filter(Boolean)
                    .map(Number);
                delete container.dataset.preOrder;
                if (container._didDrag || evt.oldIndex !== evt.newIndex) {
                    // Suppress the synthetic click that would open the task after a drag.
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
