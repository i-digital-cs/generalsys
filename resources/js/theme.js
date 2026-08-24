const STORAGE_KEY = 'hr-pro-theme';

function readStoredTheme() {
    try {
        const value = localStorage.getItem(STORAGE_KEY);

        return value === 'dark' || value === 'light' ? value : null;
    } catch {
        return null;
    }
}

function currentTheme() {
    const attr = document.documentElement.getAttribute('data-bs-theme');

    if (attr === 'dark' || attr === 'light') {
        return attr;
    }

    return readStoredTheme() ?? 'light';
}

function writeTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);

    try {
        localStorage.setItem(STORAGE_KEY, theme);
    } catch {
        // Private mode / blocked storage — theme still applies for this page.
    }
}

function syncToggleUi(theme) {
    const isDark = theme === 'dark';

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        const icon = button.querySelector('[data-theme-icon]');
        if (icon) {
            icon.classList.toggle('bi-moon-stars', ! isDark);
            icon.classList.toggle('bi-sun', isDark);
        }

        const label = button.querySelector('[data-theme-label]');
        if (label) {
            label.textContent = isDark
                ? (button.dataset.labelLight ?? '')
                : (button.dataset.labelDark ?? '');
        }

        const aria = isDark
            ? (button.dataset.ariaLight ?? '')
            : (button.dataset.ariaDark ?? '');

        if (aria) {
            button.setAttribute('aria-label', aria);
            button.setAttribute('title', aria);
        }
    });
}

export function initThemeToggle() {
    const theme = currentTheme();
    writeTheme(theme);
    syncToggleUi(theme);

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const next = currentTheme() === 'dark' ? 'light' : 'dark';
            writeTheme(next);
            syncToggleUi(next);
        });
    });
}
