// Valuation form: dynamic driver rows + live over-target (orders > 25).
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
            } else if (input.dataset.field === 'score') {
                input.id = 'driver_score_' + index;
            } else if (input.dataset.field === 'notes') {
                input.id = 'driver_notes_' + index;
            }
        });
    });
}

function refreshDriverOver(row, target, overTemplate) {
    const ordersInput = row.querySelector('[data-driver-orders]');
    const overEl = row.querySelector('[data-driver-over]');
    const okEl = row.querySelector('[data-driver-ok]');
    if (! ordersInput || ! overEl) {
        return;
    }

    const orders = parseFloat(String(ordersInput.value).replace(',', '.'));
    const value = Number.isFinite(orders) ? orders : 0;
    const over = Math.max(0, value - target);

    if (over > 0) {
        overEl.textContent = overTemplate.replace(':x', String(over));
        overEl.classList.remove('d-none');
        if (okEl) {
            okEl.classList.add('d-none');
        }
    } else {
        overEl.classList.add('d-none');
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

    const target = parseFloat(block.dataset.ordersTarget || '25') || 25;
    const overTemplate = block.dataset.overTemplate || 'Over target :x';

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
        if (event.target.matches('[data-driver-orders]')) {
            refreshDriverOver(row, target, overTemplate);
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
        refreshDriverOver(row, target, overTemplate);
    });
})();
