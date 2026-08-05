/**
 * Client-side search + select filters for list tables and card grids.
 *
 * Table:
 *   <div data-table-filter-root>
 *     <div data-table-toolbar>…input[data-table-search] …select[data-table-filter=status]</div>
 *     <table data-table-filterable>
 *       <tr data-filter-text="…" data-filter-status="IN">…</tr>
 *       <tr data-table-empty-row class="d-none"><td colspan="…">…</td></tr>
 *     </table>
 *   </div>
 *
 * Cards / non-table lists:
 *   <div data-table-filter-root>
 *     …toolbar…
 *     <div data-filterable-item data-filter-text="…">…</div>
 *     <div data-table-empty-msg class="d-none">…</div>
 *   </div>
 */
function normalize(value) {
    return String(value ?? '')
        .trim()
        .toLowerCase()
        .normalize('NFKD');
}

function filterableRows(table) {
    return Array.from(table.querySelectorAll('tbody > tr')).filter(
        (row) => ! row.hasAttribute('data-table-empty-row')
            && Array.from(row.attributes).some((attr) => attr.name.startsWith('data-filter-')),
    );
}

function matchesFilters(el, search, selects) {
    const haystack = normalize(el.getAttribute('data-filter-text'));
    const matchesSearch = search === '' || haystack.includes(search);

    const matchesSelects = selects.every((select) => {
        const key = select.getAttribute('data-table-filter');
        const wanted = normalize(select.value);

        if (wanted === '') {
            return true;
        }

        return normalize(el.getAttribute(`data-filter-${key}`)) === wanted;
    });

    return matchesSearch && matchesSelects;
}

function applyFilters(root) {
    const toolbar = root.querySelector('[data-table-toolbar]');

    if (! toolbar) {
        return;
    }

    const search = normalize(toolbar.querySelector('[data-table-search]')?.value);
    const selects = Array.from(toolbar.querySelectorAll('[data-table-filter]'));
    let visible = 0;

    root.querySelectorAll('[data-table-filterable]').forEach((table) => {
        const rows = filterableRows(table);
        const emptyRow = table.querySelector('[data-table-empty-row]');
        let tableVisible = 0;

        rows.forEach((row) => {
            const show = matchesFilters(row, search, selects);
            row.classList.toggle('d-none', ! show);

            if (show) {
                tableVisible += 1;
                visible += 1;
            }
        });

        if (emptyRow) {
            emptyRow.classList.toggle('d-none', tableVisible > 0);
        }
    });

    root.querySelectorAll('[data-filterable-item]').forEach((item) => {
        const show = matchesFilters(item, search, selects);
        item.classList.toggle('d-none', ! show);

        if (show) {
            visible += 1;
        }
    });

    const emptyMsg = root.querySelector('[data-table-empty-msg]');

    if (emptyMsg) {
        emptyMsg.classList.toggle('d-none', visible > 0);
    }
}

function bindRoot(root) {
    if (root.dataset.tableFilterBound === '1') {
        return;
    }

    root.dataset.tableFilterBound = '1';

    const onChange = (event) => {
        if (event.target.matches('[data-table-search], [data-table-filter]')) {
            applyFilters(root);
        }
    };

    root.addEventListener('input', onChange);
    root.addEventListener('change', onChange);
    applyFilters(root);
}

export function initTableFilters(scope = document) {
    scope.querySelectorAll('[data-table-filter-root]').forEach(bindRoot);
}
