/**
 * Login page: keep CSRF in sync with the live session.
 * Browser autofill / bfcache often submits a stale _token → HTTP 419.
 */
async function fetchCsrfToken() {
    const response = await fetch('/csrf-token', {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (! response.ok) {
        return null;
    }

    const data = await response.json();

    return typeof data.token === 'string' ? data.token : null;
}

function applyCsrfToken(form, token) {
    const input = form.querySelector('input[name="_token"]');
    const meta = document.querySelector('meta[name="csrf-token"]');

    if (input) {
        input.value = token;
    }

    if (meta) {
        meta.setAttribute('content', token);
    }
}

async function refreshLoginCsrf(form) {
    const token = await fetchCsrfToken();

    if (token) {
        applyCsrfToken(form, token);
    }

    return token;
}

function initLoginForm() {
    const form = document.querySelector('[data-login-form]');

    if (! form || form.dataset.loginReady === '1') {
        return;
    }

    form.dataset.loginReady = '1';

    // Back-forward cache restores HTML with a dead CSRF token.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            window.location.reload();
        }
    });

    let submitting = false;

    form.addEventListener('submit', async (event) => {
        if (submitting) {
            return;
        }

        event.preventDefault();
        submitting = true;

        const submitButton = form.querySelector('[type="submit"]');
        if (submitButton instanceof HTMLButtonElement) {
            submitButton.disabled = true;
        }

        try {
            await refreshLoginCsrf(form);
        } catch {
            // Fall through and submit with whatever token is in the form.
        }

        form.submit();
    });

    // Sync as soon as the page is interactive so autofill submits a live token.
    refreshLoginCsrf(form).catch(() => null);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLoginForm);
} else {
    initLoginForm();
}
