import * as bootstrap from 'bootstrap';

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
