/**
 * Searchable product picker with thumbnails (driver pickup + staff logistics).
 * Keeps a hidden native <select> in sync for existing form scripts.
 */
function appendThumb(parent, url, alt) {
    if (url) {
        const img = document.createElement('img');
        img.className = 'gh-product-thumb';
        img.src = url;
        img.alt = alt || '';
        img.width = 40;
        img.height = 40;
        img.loading = 'lazy';
        img.decoding = 'async';
        parent.appendChild(img);

        return;
    }
    const fb = document.createElement('span');
    fb.className = 'gh-product-thumb gh-product-thumb--fallback';
    fb.setAttribute('aria-hidden', 'true');
    fb.innerHTML = '<i class="bi bi-image"></i>';
    parent.appendChild(fb);
}

function localized(row, isAr) {
    return (isAr ? row.name_ar : row.name_en) || row.name_ar || '';
}

function initOne(root) {
    if (root.dataset.pickerReady === '1') {
        return;
    }
    root.dataset.pickerReady = '1';

    const groups = JSON.parse(root.dataset.groups || '[]');
    const isAr = root.dataset.locale === 'ar';
    const productSel = root.querySelector('[data-picker-product]');
    const list = root.querySelector('[data-picker-list]');
    const search = root.querySelector('[data-picker-search]');
    const treatmentWrap = root.querySelector('[data-picker-treatment-wrap]');
    const treatmentSel = root.querySelector('[data-picker-treatment]');
    if (! productSel || ! list) {
        return;
    }

    const render = (query = '') => {
        const q = query.trim().toLowerCase();
        list.replaceChildren();
        const filtered = groups.filter((g) => {
            if (! q) {
                return true;
            }
            const hay = `${g.code || ''} ${g.name_ar || ''} ${g.name_en || ''}`.toLowerCase();

            return hay.includes(q);
        });
        if (filtered.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'gh-product-picker-empty small text-body-secondary';
            empty.textContent = root.dataset.emptyMessage || '—';
            list.appendChild(empty);

            return;
        }
        filtered.forEach((g) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'gh-product-picker-item';
            btn.setAttribute('role', 'option');
            btn.dataset.productId = String(g.product_id);
            const selected = String(productSel.value) === String(g.product_id);
            btn.classList.toggle('is-selected', selected);
            btn.setAttribute('aria-selected', selected ? 'true' : 'false');
            const name = localized(g, isAr);
            appendThumb(btn, g.image_url, name);
            const meta = document.createElement('span');
            meta.className = 'gh-product-picker-meta';
            const title = document.createElement('span');
            title.className = 'fw-semibold';
            title.textContent = name;
            meta.appendChild(title);
            if (g.code) {
                const code = document.createElement('span');
                code.className = 'small text-body-secondary num-ltr';
                code.textContent = g.code;
                meta.appendChild(code);
            }
            btn.appendChild(meta);
            btn.addEventListener('click', () => selectProduct(g.product_id));
            list.appendChild(btn);
        });
    };

    const fillTreatments = (group) => {
        if (! treatmentSel || ! treatmentWrap) {
            return;
        }
        treatmentSel.replaceChildren();
        if (! group) {
            treatmentWrap.classList.add('d-none');

            return;
        }
        group.treatments.forEach((t) => {
            const opt = document.createElement('option');
            opt.value = t.offering_id;
            opt.textContent = localized(t, isAr);
            treatmentSel.appendChild(opt);
        });
        treatmentSel.selectedIndex = 0;
        // Show treatment control when 0 (hint: none) or 2+ choices; single treatment stays implicit.
        treatmentWrap.classList.toggle('d-none', group.treatments.length === 1);
        const noTreat = root.querySelector('[data-no-treatments]');
        noTreat?.classList.toggle('d-none', group.treatments.length > 0);
        treatmentSel.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const selectProduct = (productId) => {
        productSel.value = String(productId);
        const group = groups.find((g) => String(g.product_id) === String(productId));
        fillTreatments(group);
        productSel.dispatchEvent(new Event('change', { bubbles: true }));
        render(search?.value || '');
    };

    productSel.replaceChildren();
    const blank = document.createElement('option');
    blank.value = '';
    blank.textContent = root.dataset.placeholder || '';
    productSel.appendChild(blank);
    groups.forEach((g) => {
        const opt = document.createElement('option');
        opt.value = g.product_id;
        opt.textContent = localized(g, isAr);
        productSel.appendChild(opt);
    });

    search?.addEventListener('input', () => render(search.value));
    productSel.addEventListener('change', () => {
        const group = groups.find((g) => String(g.product_id) === String(productSel.value));
        fillTreatments(group);
        render(search?.value || '');
    });

    render('');
}

export function initGhProductPickers() {
    document.querySelectorAll('[data-gh-product-picker]').forEach(initOne);
}
