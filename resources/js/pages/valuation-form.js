// Valuation form: dynamic driver rows + live target gap (over / under).
// Names MUST be indexed (input[drivers][0][user_id]), same as employee contacts.

function reindexDriverRows(container, prefix) {
    container.querySelectorAll('[data-row]').forEach(function (row, index) {
        row.querySelectorAll('[data-field]').forEach(function (input) {
            input.name = prefix + '[' + index + '][' + input.dataset.field + ']';
            if (input.dataset.field === 'user_id') {
                input.id = 'driver_user_' + index;
            } else if (input.dataset.field === 'name') {
                input.id = 'driver_name_' + index;
            } else if (input.dataset.field === 'orders') {
                input.id = 'driver_orders_' + index;
            } else if (input.dataset.field === 'target') {
                input.id = 'driver_target_' + index;
            } else if (input.dataset.field === 'score') {
                input.id = 'driver_score_' + index;
            } else if (input.dataset.field === 'notes') {
                input.id = 'driver_notes_' + index;
            }
        });
    });
}

function parseDriverNumber(raw) {
    const value = parseFloat(String(raw || '').replace(',', '.'));

    return Number.isFinite(value) ? value : 0;
}

function refreshDriverOver(row, defaultTarget, overTemplate, underTemplate) {
    const ordersInput = row.querySelector('[data-driver-orders]');
    const targetInput = row.querySelector('[data-driver-target]');
    const overEl = row.querySelector('[data-driver-over]');
    const underEl = row.querySelector('[data-driver-under]');
    const okEl = row.querySelector('[data-driver-ok]');
    if (! ordersInput || ! overEl) {
        return;
    }

    const orders = parseDriverNumber(ordersInput.value);
    const target = targetInput
        ? parseDriverNumber(targetInput.value || defaultTarget)
        : defaultTarget;
    const safeTarget = Math.max(0, target);
    const over = Math.max(0, orders - safeTarget);
    const under = Math.max(0, safeTarget - orders);

    if (over > 0) {
        overEl.textContent = overTemplate.replace(':x', String(over));
        overEl.classList.remove('d-none');
        if (underEl) {
            underEl.classList.add('d-none');
        }
        if (okEl) {
            okEl.classList.add('d-none');
        }
    } else if (under > 0) {
        overEl.classList.add('d-none');
        if (underEl) {
            underEl.textContent = underTemplate.replace(':x', String(under));
            underEl.classList.remove('d-none');
        }
        if (okEl) {
            okEl.classList.add('d-none');
        }
    } else {
        overEl.classList.add('d-none');
        if (underEl) {
            underEl.classList.add('d-none');
        }
        if (okEl) {
            okEl.classList.remove('d-none');
        }
    }
}

function syncDriverNameField(row) {
    const select = row.querySelector('[data-driver-user]');
    const nameInput = row.querySelector('[data-driver-name]');
    if (! select || ! nameInput) {
        return;
    }

    if (select.value) {
        nameInput.value = '';
        nameInput.disabled = true;
    } else {
        nameInput.disabled = false;
    }
}

(function () {
    const block = document.querySelector('[data-drivers-block]');
    if (! block) {
        return;
    }

    const defaultTarget = parseDriverNumber(block.dataset.defaultOrdersTarget || '25') || 25;
    const overTemplate = block.dataset.overTemplate || 'Over target :x';
    const underTemplate = block.dataset.underTemplate || 'Under target :x';

    document.querySelectorAll('[data-add-row]').forEach(function (button) {
        button.addEventListener('click', function () {
            const template = document.getElementById(button.dataset.addRow);
            const targetEl = document.getElementById(button.dataset.target);
            const prefix = button.dataset.prefix;

            if (! template || ! targetEl || ! prefix) {
                return;
            }

            targetEl.appendChild(template.content.cloneNode(true));
            reindexDriverRows(targetEl, prefix);
            const last = targetEl.querySelector('[data-row]:last-child');
            if (last) {
                syncDriverNameField(last);
                refreshDriverOver(last, defaultTarget, overTemplate, underTemplate);
            }
        });
    });

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-remove-row]');
        if (! button || ! button.closest('[data-drivers-block]')) {
            return;
        }

        const row = button.closest('[data-row]');
        const container = row && row.parentElement;

        if (row) {
            row.remove();
        }

        if (container && container.dataset.prefix) {
            reindexDriverRows(container, container.dataset.prefix);
        }
    });

    block.addEventListener('input', function (event) {
        const row = event.target.closest('[data-driver-row]');
        if (! row) {
            return;
        }
        if (event.target.matches('[data-driver-orders], [data-driver-target]')) {
            refreshDriverOver(row, defaultTarget, overTemplate, underTemplate);
        }
    });

    block.addEventListener('change', function (event) {
        const row = event.target.closest('[data-driver-row]');
        if (row && event.target.matches('[data-driver-user]')) {
            syncDriverNameField(row);
        }
    });

    block.querySelectorAll('[data-driver-row]').forEach(function (row) {
        syncDriverNameField(row);
        refreshDriverOver(row, defaultTarget, overTemplate, underTemplate);
    });
})();
