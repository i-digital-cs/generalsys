// Clock screen: after choosing punch photos, show a description per file.

function renderClockPhotoCaptions(root) {
    const input = root.querySelector('[data-clock-photos-input]');
    const box = root.querySelector('[data-clock-photos-captions]');
    if (! input || ! box) {
        return;
    }

    const files = input.files ? Array.from(input.files) : [];
    box.innerHTML = '';

    if (files.length === 0) {
        box.classList.add('d-none');
        return;
    }

    box.classList.remove('d-none');
    const labelText = box.dataset.label || 'Description';
    const placeholder = box.dataset.placeholder || '';
    const fileLabel = box.dataset.fileLabel || 'Photo :n';
    const required = box.dataset.required === '1' || root.dataset.required === '1';

    files.forEach(function (file, index) {
        const wrap = document.createElement('div');
        wrap.className = 'mb-2';

        const label = document.createElement('label');
        label.className = 'form-label small mb-1';
        const id = (input.id || 'photos') + '-desc-' + index;
        label.setAttribute('for', id);
        const numbered = fileLabel.replace(':n', String(index + 1));
        label.textContent = numbered + ' — ' + labelText + (file.name ? ' (' + file.name + ')' : '');

        const field = document.createElement('input');
        field.type = 'text';
        field.className = 'form-control form-control-sm';
        field.name = 'photo_descriptions[]';
        field.id = id;
        field.maxLength = 500;
        field.placeholder = placeholder;
        field.autocomplete = 'off';
        if (required) {
            field.required = true;
        }

        wrap.appendChild(label);
        wrap.appendChild(field);
        box.appendChild(wrap);
    });
}

document.querySelectorAll('[data-clock-photos]').forEach(function (root) {
    const input = root.querySelector('[data-clock-photos-input]');
    if (! input) {
        return;
    }

    input.addEventListener('change', function () {
        renderClockPhotoCaptions(root);
    });
});
