// Employee create/edit form: dynamic add/remove rows for qualifications
// and emergency contacts (docs/UI_FRAMEWORK.md §10 — vanilla JS only).
//
// Names MUST be indexed (qualifications[0][degree], not qualifications[][degree]).
// PHP treats every empty [] as a NEW array element, which splits one visual row
// into three partial rows and breaks required_with validation.
function reindexRows(container, prefix) {
    container.querySelectorAll('[data-row]').forEach(function (row, index) {
        row.querySelectorAll('[data-field]').forEach(function (input) {
            input.name = prefix + '[' + index + '][' + input.dataset.field + ']';
        });
    });
}

document.querySelectorAll('[data-add-row]').forEach(function (button) {
    button.addEventListener('click', function () {
        const template = document.getElementById(button.dataset.addRow);
        const target = document.getElementById(button.dataset.target);
        const prefix = button.dataset.prefix;

        if (! template || ! target || ! prefix) {
            return;
        }

        target.appendChild(template.content.cloneNode(true));
        reindexRows(target, prefix);
    });
});

document.addEventListener('click', function (event) {
    const button = event.target.closest('[data-remove-row]');

    if (! button) {
        return;
    }

    const row = button.closest('[data-row]');
    const container = row && row.parentElement;

    if (row) {
        row.remove();
    }

    if (container && container.dataset.prefix) {
        reindexRows(container, container.dataset.prefix);
    }
});

// Shift type change prefills the default work window (ShiftConfig::DEFAULT_TIME_WINDOWS).
// The time inputs stay editable — this only sets a sensible starting value.
(function () {
    const shiftSelect = document.getElementById('shift_type_default');

    if (! shiftSelect || ! shiftSelect.dataset.shiftTimes) {
        return;
    }

    const windows = JSON.parse(shiftSelect.dataset.shiftTimes);
    const startInput = document.getElementById('work_start_time');
    const endInput = document.getElementById('work_end_time');

    shiftSelect.addEventListener('change', function () {
        const window = windows[shiftSelect.value];

        if (! window) {
            return;
        }

        if (startInput) {
            startInput.value = window[0];
        }

        if (endInput) {
            endInput.value = window[1];
        }
    });
})();
