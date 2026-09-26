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
// Hours on the same row = start→end span (overnight when end ≤ start).
(function () {
    const root = document.querySelector('[data-default-shifts]');
    if (! root || ! root.dataset.shiftTimes) {
        return;
    }

    const windows = JSON.parse(root.dataset.shiftTimes);

    function minutesFromTime(value) {
        if (! value || value.indexOf(':') === -1) {
            return null;
        }
        const parts = value.split(':');
        const hours = parseInt(parts[0], 10);
        const minutes = parseInt(parts[1], 10);
        if (Number.isNaN(hours) || Number.isNaN(minutes)) {
            return null;
        }

        return (hours * 60) + minutes;
    }

    function windowHours(start, end) {
        const from = minutesFromTime(start);
        const to = minutesFromTime(end);
        if (from === null || to === null) {
            return null;
        }
        let span = to - from;
        if (span <= 0) {
            span += 24 * 60;
        }

        return Math.round((span / 60) * 100) / 100;
    }

    function formatHours(hours) {
        return hours.toFixed(2) + 'h';
    }

    function syncHours(row) {
        const startInput = row.querySelector('[data-default-shift-start]');
        const endInput = row.querySelector('[data-default-shift-end]');
        const hoursEl = row.querySelector('[data-default-shift-hours]');
        if (! hoursEl) {
            return;
        }

        const hours = windowHours(
            startInput ? startInput.value : '',
            endInput ? endInput.value : '',
        );
        hoursEl.textContent = hours === null ? '—' : formatHours(hours);
    }

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
                syncHours(row);
                return;
            }

            const window = windows[type];
            if (window) {
                if (startInput) startInput.value = window[0];
                if (endInput) endInput.value = window[1];
            }
            if (startInput) startInput.required = true;
            if (endInput) endInput.required = true;
            syncHours(row);
        });

        if (startInput) {
            startInput.addEventListener('input', function () { syncHours(row); });
            startInput.addEventListener('change', function () { syncHours(row); });
        }
        if (endInput) {
            endInput.addEventListener('input', function () { syncHours(row); });
            endInput.addEventListener('change', function () { syncHours(row); });
        }

        syncHours(row);
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

(function () {
    const role = document.getElementById('role');
    const salesLoss = document.querySelector('[data-sales-loss-flag]');
    const pagesRoot = document.querySelector('[data-gross-pages]');
    if (! role || ! pagesRoot) {
        return;
    }

    const checks = pagesRoot.querySelector('[data-gross-pages-checks]');
    const modulesRoot = pagesRoot.querySelector('[data-valuation-modules]');
    const productCheck = pagesRoot.querySelector('[data-valuation-product]');
    const help = pagesRoot.querySelector('[data-gross-pages-help]');
    const managerHint = pagesRoot.querySelector('[data-gross-pages-manager-hint]');
    const pagesHelp = pagesRoot.querySelector('[data-gross-pages-pages-help]');
    const pagesLabel = pagesRoot.querySelector('[data-gross-pages-pages-label]');
    let salesDefaults = [];
    let accountantDefaults = [];
    try {
        salesDefaults = JSON.parse(pagesRoot.dataset.defaultsSales || '[]');
        accountantDefaults = JSON.parse(pagesRoot.dataset.defaultsAccountant || '[]');
    } catch (e) {
        salesDefaults = [];
        accountantDefaults = [];
    }

    function setChecksEnabled(enabled) {
        if (checks) {
            checks.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
                input.disabled = ! enabled;
            });
            checks.classList.toggle('d-none', ! enabled);
        }
        if (modulesRoot) {
            modulesRoot.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
                input.disabled = ! enabled;
            });
            modulesRoot.classList.toggle('d-none', ! enabled);
        }
        if (pagesHelp) {
            pagesHelp.classList.toggle('d-none', ! enabled);
        }
        if (pagesLabel) {
            pagesLabel.classList.toggle('d-none', ! enabled);
        }
    }

    function applyDefaults(keys) {
        if (! checks) {
            return;
        }
        checks.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
            input.checked = keys.indexOf(input.value) !== -1;
        });
        ensureProductWhenPages();
    }

    function ensureProductWhenPages() {
        if (! productCheck || ! checks) {
            return;
        }
        const anyPage = Array.prototype.some.call(
            checks.querySelectorAll('input[type="checkbox"]'),
            function (input) {
                return input.checked;
            },
        );
        if (anyPage) {
            productCheck.checked = true;
        }
    }

    function syncGrossPagesUi() {
        const isManager = role.value === 'MANAGER';
        const isSales = role.value === 'SALES';

        if (salesLoss) {
            salesLoss.classList.toggle('d-none', ! isSales);
        }

        if (managerHint) {
            managerHint.classList.toggle('d-none', ! isManager);
        }
        if (help) {
            help.classList.toggle('d-none', isManager);
        }

        setChecksEnabled(! isManager);

        if (role.dataset.grossPagesTouched === '1') {
            if (role.value === 'SALES') {
                applyDefaults(salesDefaults);
                if (productCheck) {
                    productCheck.checked = true;
                }
            } else if (role.value === 'ACCOUNTANT') {
                applyDefaults(accountantDefaults);
                if (productCheck) {
                    productCheck.checked = true;
                }
            } else if (! isManager) {
                applyDefaults([]);
            }
        }
    }

    if (checks) {
        checks.addEventListener('change', ensureProductWhenPages);
    }

    role.addEventListener('change', function () {
        role.dataset.grossPagesTouched = '1';
        syncGrossPagesUi();
    });
    syncGrossPagesUi();
})();
