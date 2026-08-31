# Mohra Gross Module (Phase 10 MVP)

**Scope:** Mohra (`product`) only. Source of truth with `docs/DATABASE_SCHEMA.md` §22 and `AGENTS.md`.

Do **not** invent rules for money, stock, invoice status, permissions, conversion, returns, or losses. If ambiguous, stop and ask.

---

## 1. Access

- User must pass `AppModule::mayAccessModule($user, 'product')` (Manager always; others need `valuation_assignments.module = product`).
- Gross routes live under `/product/gross/...`.
- Sidebar Gross items appear only in the Product/Mohra menu.

---

## 2. Units & conversion

- **Base stock unit = `PIECE`.** All balances and allocation math use integer pieces.
- Unique `code` is **auto-generated** on create as `ITM-00001`, `ITM-00002`, … (service-only; not editable after create).
- Product UI uses **pieces only** (carton conversion field removed from forms; internal `pieces_per_carton` stays `1`).
- Categories: main (`parent_id` null) + subcategory (has parent). Manage under Gross → Categories (search, edit, Excel import/template), then pick on the item form.
- **Color / size / model** are admin lists (`gross_item_attributes`), not free text — manage under Gross → Color / Size / Model (tabs + Excel import/template), then select on the item form (filters use the same lists). Item **name** stays free text with datalist suggestions from existing names.
- **Images:** up to 10 per item (JPG/PNG/WebP, ≤5MB each), stored on `local` disk under `gross-item-images/{item_id}/`, served via authorized route `gross.items.images.show`.
- **Excel import** on items list: template + import (`name`, `color_en`, `size_en`, `model_en`, `category_en`, `subcategory_en`, …). Lookups must already exist; duplicates by name+color+size+model skipped.
- **Customers:** Excel template + import (`name`, `phone`, `address`); duplicate phones skipped. List supports search, inline edit, and delete (blocked if customer has invoices).
- **Shipping companies:** same list UX — search, inline edit, delete (blocked if used on invoices/allocations), name suggestions, Excel import (`name`, `phone`, `address`, `notes`, `status`; duplicate names skipped). **Show page** (`/product/gross/shipping/{id}`): invoice counts by status, piece buckets, shipping fees by currency/status, and paginated invoices.
- **Purchase invoices (T-11.2):** Manager/Accountant. Multi-line form (item, qty, buy price, sale price, line + invoice discount amount or percent). Confirm → `PURCHASE_IN` stock in. Numbers `MP-YYYYMM-####`.
- **Sales invoices:** multi-line form (add/remove rows, filter item lists up to 500 active items). Invoice discount and each line discount are **amount or percent** (not both). Selecting a saved customer fills name/phone/address. **Sales person** (`sales_user_id`) picker (active `SALES` users + current editor). Line table shows **size**; live JS line totals and header totals (subtotal → discounts → shipping → grand total). **Show page** is a printable invoice document (number, status, customer, sales person, shipping, lines with size, totals); operational actions stay in a non-print toolbar. **PDF** (`gross.sales.pdf`) and **Excel** (`gross.sales.excel`) via `SalesInvoiceExportService`.
- **Stock movements ledger:** read-only paginated list at `gross.stock.movements` (filter by item/date/type); link from stock index.
- Per item (legacy column kept): `pieces_per_carton` fixed to `1` in ItemService.
  - `PIECE` → conversion factor `1`
  - `CARTON` → conversion factor = `pieces_per_carton`
- No system-wide conversion.
- No decimal pieces. Entered qty must be a positive integer.
- `qty_pieces = qty × conversion_factor` (always integer).

**Immutability:** If an item has any `gross_stock_movements` row, **`pieces_per_carton` cannot change**. Historical snapshots never mutate.

**Pricing ≠ conversion:** Line `unit_price` is for the selected unit. Do not derive carton price from piece price.

---

## 3. Stock source of truth

| Layer | Role |
|-------|------|
| `gross_stock_movements` | Audit ledger (source of history) |
| `gross_stock_balances` | Materialized current pieces buckets |

Only **`StockService`** may create movements and update balances (inside a DB transaction with `lockForUpdate` on the balance row).

Buckets on balance: `qty_on_hand_pieces`, `qty_with_courier_pieces`, `qty_lost_pieces`.

Low stock: `qty_on_hand_pieces ≤ reorder_level` (`reorder_level` in pieces, default 5). Stock screen: search + low-stock filter.

---

## 4. Shipment allocation invariant

All quantities are pieces:

```text
qty_shipped_pieces =
  qty_delivered_pieces
  + qty_returned_pieces
  + qty_with_courier_pieces
  + qty_lost_pieces
```

On confirm sale: `ON_HAND − qty_pieces`; allocation starts with `qty_with_courier_pieces = qty_shipped_pieces`.

---

## 5. Invoice state machine

Statuses: `DRAFT | CONFIRMED | DELIVERED | REFUSED | CANCELLED`

```text
DRAFT
  → CONFIRMED          (stock out + allocations)
  → CANCELLED          (no stock impact)

CONFIRMED
  → DELIVERED          (remaining with_courier → delivered on allocation; WITH_COURIER − on balance)
  → REFUSED            (client refused — see §6)
  → CANCELLED          (only if every allocation has qty_with_courier_pieces = 0
                        AND no further silent restock of courier qty;
                        if still WITH_COURIER → refuse/return first)
```

- Controllers/frontend never set `status` arbitrarily.
- Transitions only via `SalesInvoiceService` / `AllocationService`.
- Operations are **idempotent**: second confirm/return/loss/refuse/cancel must not double-apply stock (422 or no-op after success).

Currency is immutable after `CONFIRMED`.

---

## 6. Client refused

Who: Manager, Accountant, Sales (Mohra-assigned).

```text
CONFIRMED → REFUSED
shipping_fee_status = LOST
```

For each allocation, `return_to_stock = qty_with_courier_pieces` (computed in service, not from the request). After:

- `qty_returned_pieces += return_to_stock`
- `qty_with_courier_pieces = 0`
- lost qty unchanged
- `ON_HAND += return_to_stock` + movement `REFUSE_RETURN`

One DB transaction.

---

## 7. Partial return & item loss

- **Return:** qty ≤ current `qty_with_courier_pieces` → ON_HAND up, allocation returned up / with_courier down. Movement `SALE_RETURN`.
- **Loss confirm:** requires capability `gross.loss.confirm` (see §8). Same qty cap. Movement `LOSS_CONFIRM`. Never restores stock.

---

## 8. Permissions

| Action | Who |
|--------|-----|
| Browse Gross (Mohra) | Mohra assignment / Manager |
| Items / shipping / customers / suppliers CRUD | Manager, Accountant (Mohra) |
| Purchase invoices create/confirm | Manager, Accountant (Mohra) — **not** Team Leader (deferred) |
| Stock adjustment | Manager, Accountant |
| Create/confirm sales invoice | Manager, Accountant, Sales |
| Collect shipping fee / mark delivered | Manager, Accountant, Sales |
| Client refuse | Manager, Accountant, Sales |
| Confirm item loss | Capability `gross.loss.confirm`: default **Manager + Accountant**; Sales only if `users.can_confirm_gross_loss = 1` |

---

## 9. Snapshots

Invoice header: customer name/phone/address, shipping company name.  
Invoice line: item code/name/color/size/model, conversion_factor, qty, unit, qty_pieces, unit_price.

FKs remain; historical UI uses snapshots.

---

## 10. Invoice numbers

Format `MH-YYYYMM-####`, `UNIQUE(invoice_number)`, generated inside a transaction using `gross_invoice_sequences` (month lock).

---

## 11. Phase 11 — Purchases / treasury / collections

Build order (see `docs/TASKS.md` T-11.1–T-11.7). Do **not** invent money rules beyond what is locked here.

| Task | Scope |
|------|--------|
| T-11.1 | **Suppliers CRUD** — mirror customers: search, edit modal, delete, name suggestions, Excel template/import (`name`, `phone`, `address`, `notes`; duplicate phones skipped). Table `gross_suppliers`. |
| T-11.2 | **Purchase invoices** — Manager/Accountant only (no TL in this task). Draft create/edit; confirm → stock `PURCHASE_IN` (ON_HAND + qty_pieces). Lines: item, qty, buy price, sale price, line discount (amount or %). Invoice discount amount or %. Numbers `MP-YYYYMM-####`. Confirmed invoices are not cancelled here (returns = T-11.3). Sale price on confirm updates item `default_sale_price` when > 0. |
| T-11.3 | **Purchase returns** — Manager/Accountant. Partial return against **CONFIRMED** purchase: qty per line ≤ remaining (`qty_pieces − qty_returned_pieces`). Creates `gross_purchase_returns` (`MR-YYYYMM-####`) + lines; stock `PURCHASE_RETURN` (ON_HAND −). Cannot over-return. Invoice stays CONFIRMED. |
| T-11.4 | **Supplier account statement** — chronological money lines: confirmed purchase `total` debit (+), purchase return totals credit (−), treasury **PAYMENT** vouchers credit (−). Running balance = purchases − returns − payments per currency. Filters: currency, from, to. No aging / due dates. |
| T-11.5 | **Courier collection (locked)** — **Shipping fee only** (not full COD). `shipping_fee_status`: `PENDING` → `COLLECTED` (idempotent). `LOST` stays refuse-path. Who: Manager, Accountant, Sales (Mohra). Invoice stays `CONFIRMED` until fee is clear (`NONE` if no fee, or `COLLECTED`); then **Mark delivered** → `DELIVERED`. Cannot mark `DELIVERED` while fee is `PENDING`. **Does not** post treasury. |
| T-11.6 | **Cash/bank treasury (locked)** — two accounts only: Cash + one Bank, both EGP. **PAYMENT** / **RECEIPT** vouchers. Who: Manager + Accountant. Numbers `PV-` / `RV-YYYYMM-####`. Posted vouchers not edited/deleted. Collecting T-11.5 fee does **not** auto-post. |
| T-11.7 | **Expense types + purpose (locked)** — Admin list `gross_expense_types` (name AR+EN, active). Voucher **purpose** `SUPPLIER` \| `EXPENSE`. SUPPLIER requires `supplier_id` (payment credits supplier statement). EXPENSE requires `expense_type_id` (electricity, rent, …; does **not** hit supplier statement). Same cash/bank balance rules. |

**Locked for MVP Phase 10 (still applies):** Lost shipping fees from `REFUSED` feed future courier statements (T-11.5).

### 11.6 Treasury (locked)

```text
Accounts: CASH + BANK, currency EGP only (seeded; unique kind+currency).
Balance (computed): opening_balance + RECEIPT − PAYMENT. Never stored as a column.
Voucher type: PAYMENT (اذن صرف) | RECEIPT (اذن تحصيل)
Purpose: SUPPLIER | EXPENSE
  SUPPLIER → requires supplier_id; PAYMENT credits supplier statement
  EXPENSE  → requires expense_type_id from admin list; never on supplier statement
Who: Manager, Accountant. Not Sales.
Vouchers: PV-YYYYMM-#### / RV-YYYYMM-####. No edit/delete/reverse in this task.
Overpaying a supplier is allowed (negative supplier balance).
Expense types: seed Electricity / Rent / Other; Manager/Accountant CRUD.
```

UI: `/product/gross/treasury` · `/product/gross/expense-types`

### 11.5 Courier collection (locked)

```text
Confirm sale with shipping_fee > 0  →  shipping_fee_status = PENDING
Refuse                              →  shipping_fee_status = LOST
Collect shipping fee                →  PENDING → COLLECTED  (invoice status stays CONFIRMED)
Mark delivered                      →  CONFIRMED → DELIVERED
  moves remaining qty_with_courier_pieces → qty_delivered_pieces per allocation;
  stock balance WITH_COURIER − (movement SALE_DELIVERED)
  only if shipping_fee_status ∈ {NONE, COLLECTED}
```

UI: pending list + actions on sales invoice show.
