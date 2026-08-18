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

// Default shift rows: choosing a type prefills that row's start/end (assign-shift style).
(function () {
    const root = document.querySelector('[data-default-shifts]');
    if (! root || ! root.dataset.shiftTimes) {
        return;
    }

    const windows = JSON.parse(root.dataset.shiftTimes);

    root.querySelectorAll('[data-default-shift-row]').forEach(function (row) {
        const typeSelect = row.querySelector('[data-default-shift-type]');
        const startInput = row.querySelector('[data-default-shift-start]');
        const endInput = row.querySelector('[data-default-shift-end]');

        if (! typeSelect) {
            return;
        }

        typeSelect.addEventListener('change', function () {
            const type = typeSelect.value;
            if (! type) {
                if (startInput) startInput.value = '';
                if (endInput) endInput.value = '';
                if (startInput) startInput.required = false;
                if (endInput) endInput.required = false;
                return;
            }

            const window = windows[type];
            if (window) {
                if (startInput) startInput.value = window[0];
                if (endInput) endInput.value = window[1];
            }
            if (startInput) startInput.required = true;
            if (endInput) endInput.required = true;
        });
    });
})();

(function () {
    const role = document.getElementById('role');
    const fields = document.querySelectorAll('[data-team-leader-fields]');
    const first = document.getElementById('team_leader_ids_0');
    const second = document.getElementById('team_leader_ids_1');

    if (! role || fields.length === 0) {
        return;
    }

    const hiddenRoles = ['ACCOUNTANT', 'ASSISTANT', 'TEAM_LEADER'];

    function syncExclusiveLeaders() {
        if (! first || ! second) {
            return;
        }

        if (first.value && first.value === second.value) {
            second.value = '';
        }

        const firstVal = first.value;
        const secondVal = second.value;

        Array.from(first.options).forEach(function (option) {
            if (! option.value) {
                return;
            }
            const taken = option.value === secondVal;
            option.hidden = taken;
            option.disabled = taken;
        });

        Array.from(second.options).forEach(function (option) {
            if (! option.value) {
                return;
            }
            const taken = option.value === firstVal;
            option.hidden = taken;
            option.disabled = taken;
        });
    }

    function syncTeamLeaderFields() {
        const hide = hiddenRoles.indexOf(role.value) !== -1;

        fields.forEach(function (el) {
            el.classList.toggle('d-none', hide);
        });

        if (first) {
            first.required = ! hide && role.value === 'EMPLOYEE';
            if (hide) {
                first.value = '';
            }
        }

        if (second && hide) {
            second.value = '';
        }

        syncExclusiveLeaders();
    }

    role.addEventListener('change', syncTeamLeaderFields);
    syncTeamLeaderFields();

    if (first) {
        first.addEventListener('change', syncExclusiveLeaders);
    }
    if (second) {
        second.addEventListener('change', syncExclusiveLeaders);
    }
})();
