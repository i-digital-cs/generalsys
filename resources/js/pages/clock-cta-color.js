export function clockCtaColor(deltaMinutes, windows) {
    const orangeBefore = windows.orange_before ?? 16;
    const orangeAfter = windows.orange_after ?? 31;
    const greenBefore = windows.green_before ?? 5;
    const greenAfter = windows.green_after ?? 5;
    const yellowBefore = windows.yellow_before ?? 15;
    const yellowAfter = windows.yellow_after ?? 30;

    if (deltaMinutes <= -orangeBefore || deltaMinutes >= orangeAfter) {
        return 'orange';
    }
    if (deltaMinutes >= -greenBefore && deltaMinutes <= greenAfter) {
        return 'green';
    }
    if (deltaMinutes >= -yellowBefore && deltaMinutes <= yellowAfter) {
        return 'yellow';
    }

    return 'red';
}

function truncateToMinute(ms) {
    return Math.floor(ms / 60000) * 60000;
}

function applyButton(btn) {
    const windows = JSON.parse(btn.getAttribute('data-clock-cta-windows') || '{}');
    let iso = btn.getAttribute('data-clock-cta-at') || '';

    const select = document.querySelector('[data-clock-shift-select]');
    const startsRaw = btn.getAttribute('data-clock-cta-starts');
    if (select && startsRaw) {
        const starts = JSON.parse(startsRaw);
        if (Object.prototype.hasOwnProperty.call(starts, select.value)) {
            iso = starts[select.value] || '';
        }
    }

    const colors = ['green', 'yellow', 'orange', 'red'];
    colors.forEach((name) => btn.classList.remove('clock-cta--' + name));

    if (! iso) {
        btn.classList.add('clock-cta--red');
        return;
    }

    const expected = Date.parse(iso);
    if (Number.isNaN(expected)) {
        btn.classList.add('clock-cta--red');
        return;
    }

    const delta = (truncateToMinute(Date.now()) - truncateToMinute(expected)) / 60000;
    btn.classList.add('clock-cta--' + clockCtaColor(delta, windows));
}

export function initClockCtaColors() {
    const tick = () => {
        document.querySelectorAll('[data-clock-cta]').forEach((btn) => applyButton(btn));
    };

    tick();
    window.setInterval(tick, 15000);
    document.addEventListener('visibilitychange', () => {
        if (! document.hidden) {
            tick();
        }
    });

    const select = document.querySelector('[data-clock-shift-select]');
    if (select) {
        select.addEventListener('change', tick);
    }
}
