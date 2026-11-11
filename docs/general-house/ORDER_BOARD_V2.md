# GH Order Board V2.4 — status → tab mapping

UI-only filters. Persisted lifecycle remains `GhOrderStateService` /
`GhOrderStatus` (`LATER → PICKUP → WORKING → DELIVERY → COMPLETED`).

| Tab | Admin / Manager | Driver |
|-----|-----------------|--------|
| Later | `status = LATER` | Assigned pickup driver + `LATER` outside lead window |
| Actions | Attention filter (overdue pickup, missing stage driver, failed attempts) — **not** a status | Failed follow-ups + overdue pickups assigned to me |
| Pickup | `status = PICKUP` | Actionable pickups (`PICKUP` + `LATER` inside lead window) |
| Working | `status = WORKING` | `WORKING` where I am pickup **or** delivery driver (**read-only**) |
| Delivery | `status = DELIVERY` | Assigned delivery driver + `DELIVERY` |
| Complete | `status = COMPLETED` | `COMPLETED` where I was pickup or delivery driver |

## Area grouping

- Zone from `order.zone` (fallback `address.zone`).
- Area order: `zones.sort_order`, then localized name.
- Missing zone → **Unassigned area**.
- Within area: schedule datetime for the stage, then `order.id`.
- Sequence `#n` is display-only within the active area + **current page** (not a DB column).

## Pagination

- Admin: **25** orders/page (`GhOrderBoardService::ADMIN_PER_PAGE`).
- Driver: **25** orders/page (`DRIVER_PER_PAGE`).
- Tab counts remain full totals (not page-scoped).
- Area grouping applies to the current page slice.
- Driver route reorder (pickup/delivery) stays enabled only when the tab fits on a single page.

## Visual skin (V2.4.2)

Scoped to `.gh-order-board` only (does not change global ERP chrome):

- Cream `#F8F7EF`, navy `#101044`, yellow `#FFD329` (owner reference ops palette).
- Official GNRL logo/mark remains cyan; board uses the ops reference palette.
- **Layout:** container queries on `.gh-order-board` (`container-name: gh-board`)
  - **1** card / row — default (mobile)
  - **2** cards — `@container gh-board (min-width: 40rem)`
  - **3** cards — `@container gh-board (min-width: 64rem)` (preferred on mid desktops)
  - **4** cards max — `@container gh-board (min-width: 86rem)` (wide desktops only)
  - Never uses `auto-fill` + small `minmax` (that produced 5–6 skinny columns).
- Card structure:
  1. Header — reference (never truncated) + badges
  2. **3 unequal inline cells** `[icon][value]`: seq (fixed) | customer | address
  3. **4 unequal inline cells** `[icon][value]`: date | time | kind | type
  4. Action row — primary Open pickup/delivery + View + Maps (navy/ghost)
- Never collapses 3/4 metadata grids to a single column on mobile.
- Order references stay navy; red is reserved for the overdue badge only.

## Driver movement (restored)

V2 board originally demoted the GH-7 task entry to a generic “View” label. V2.4 restores
explicit pickup/delivery CTAs on actionable cards (`data-gh-task-action`) that open the
same `gh.driver.pickup` / `gh.driver.delivery` workspaces. Confirm/fail still run through
`OrderLogisticsController` → `GhOrderStateService` (permissions + payment rules unchanged).

Working / Later / Complete remain read-only for drivers (no Maps / no execute CTA).

## Driver drag-and-drop reorder (V2.4.1)

**Root cause of V2 regression:** SortableJS was bound to a wrapper whose children were
`section.gh-board-area`, while `[data-sort-item]` cards were nested inside each area list.
`querySelectorAll(':scope > [data-sort-item]')` returned nothing — handles were visible but
inert. Additionally `groupByArea()` re-sorted by schedule and wiped `gh_driver_list_positions`.

**Restored behaviour:**
- Each `.gh-board-area-list` is a Sortable container (direct `[data-sort-item]` children).
- Cross-area / cross-stage moves are blocked (`group.pull/put: false`).
- Persist via existing `POST gh.driver.reorder-pickups|reorder-deliveries` with full board
  DOM order under `[data-gh-board-sort-root]`.
- Reorder UI enabled only when `last_page === 1` (entire actionable list on one page).
  Multi-page reorder is **not** supported — handles are omitted to avoid misleading UX.

## RTL / LTR (V2.4.2)

Cards use CSS logical properties (`text-align: start`, `justify-content: start`,
`margin-inline-*`, `padding-inline-*`). Metadata cells are `inline-flex` row with
DOM order `[icon][value]` — in RTL the row starts at inline-start (right), so the
icon visually precedes the value from the right. No per-icon `row-reverse` hacks.
`num-ltr` is limited to references / dates / times / sequence numbers.

## Order type

Display always **Normal** / **عادي** (`NORMAL`). Express label reserved in UI lang only — no pricing/ops logic.
