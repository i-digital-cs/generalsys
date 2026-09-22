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
- **Customers:** Excel template + import (`name`, `phone`, `address`); duplicate phones skipped. List supports search, inline edit, and delete (blocked if customer has invoices). New name/phone on a sales invoice **auto-creates/updates** Customers (match by phone).
- **Shipping companies:** same list UX — search, inline edit, delete (blocked if used on invoices/allocations), name suggestions, Excel import (`name`, `phone`, `address`, `notes`, `status`; duplicate names skipped). On sales invoices, pick existing **or type a new name** — saved to Shipping companies. **Show page** (`/product/gross/shipping/{id}`): invoice counts by status, piece buckets, shipping fees by currency/status, and paginated invoices.
- **Purchase invoices (T-11.2):** Manager/Accountant. Multi-line form (item, qty, buy price, sale price, line + invoice discount amount or percent). Confirm → `PURCHASE_IN` stock in. Numbers `MP-YYYYMM-####`. New supplier name/phone **auto-creates/updates** Suppliers. **Excel import** on purchases index (`invoice_group` → DRAFT; `item_code` required).
- **Sales invoices:** multi-line form (add/remove rows, typeahead item search). Invoice discount and each line discount are **amount or percent** (not both). Selecting a saved customer fills name/phone/address; typed clients/shipping companies are persisted. **Excel import** on sales index (`invoice_group` → DRAFT). **Sales person** (`sales_user_id`) picker (active `SALES` users + current editor). Line table shows **size**; live JS line totals and header totals (subtotal → discounts → shipping → grand total). **Show page** is a printable invoice document (number, status, customer, sales person, shipping, lines with size, totals); operational actions stay in a non-print toolbar. **PDF** (`gross.sales.pdf`) and **Excel** (`gross.sales.excel`) via `SalesInvoiceExportService`. **Cashier / POS create (T-14.2):** second create path (`gross.sales.cashier`) — category → subcategory → product-name cards (image/name/description) → sibling size/color modal → cart sidebar; posts the same draft payload to `gross.sales.store`. Classic form remains on `gross.sales.create`.
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

On hand-to-courier: `ON_HAND − qty_pieces`; allocation starts with `qty_with_courier_pieces = qty_shipped_pieces`. Confirm alone does **not** move stock.

---

## 5. Invoice state machine

Statuses: `DRAFT | CONFIRMED | DELIVERED | REFUSED | CANCELLED`

Fulfillment (while `CONFIRMED`): `PREPARING → READY_WAREHOUSE → WITH_COURIER`

```text
DRAFT
  → CONFIRMED + PREPARING   (no stock movement; packing starts)
  → CANCELLED               (no stock impact)

CONFIRMED + PREPARING
  → READY_WAREHOUSE         (packing image required)
  → CANCELLED               (still no stock out)

CONFIRMED + READY_WAREHOUSE
  → WITH_COURIER            (hand to courier: SALE_OUT + allocations + COD flags)
  → CANCELLED               (still no stock out)

CONFIRMED + WITH_COURIER
  → DELIVERED               (remaining with_courier → delivered on allocation; WITH_COURIER − on balance)
  → REFUSED                 (client refused — see §6)
  → CANCELLED               (only if every allocation has qty_with_courier_pieces = 0
                            AND no further silent restock of courier qty;
                            if still WITH_COURIER → refuse/return first)
```

- Controllers/frontend never set `status` / `fulfillment_status` arbitrarily.
- Transitions only via `SalesInvoiceService` / `AllocationService`.
- Operations are **idempotent**: second confirm/hand-to-courier/return/loss/refuse/cancel must not double-apply stock (422 or no-op after success).

Currency is immutable after `CONFIRMED`.

Sidebar queues under Sales → مسار الشحنة: preparing / ready / with_courier / delivered / returned.
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

- **Client partial return:** qty ≤ deliverable with courier (`with_courier − returned_pending`). Money adjusts immediately (`qty_returned_pieces` up). **Pieces stay with courier** until a separate **Receive pieces from courier** action (or refuse handover). Audit movement `SALE_RETURN_CLIENT` (no stock move).
- **Full client refuse:** same as partial but for all remaining pieces — money zeroed, **pieces stay with courier** until **Receive pieces from courier**. Delivery fee (`shipping_fee`) becomes **owed to shipping company** (shown on invoice + collection statement as `DELIVERY_FEE`).
- **Handover from courier:** `receiveReturnedPiecesFromCourier` / **Receive pieces from courier** moves refused pieces: WITH_COURIER −, ON_HAND +. Movement `SALE_RETURN` (partial) or `REFUSE_RETURN` (full refuse). **Independent of Collect COD** — money and stock handover may happen in either order. **Per-line partial qty** allowed (`receive-returned-line` with `qty_pieces` ≤ pending for that allocation); top button still receives all pending lines.
- **Collect COD:** money only (`cod_collection_status` PENDING → COLLECTED). Does **not** move returned pieces into warehouse.
- **Deliver:** only deliverable qty (`with_courier − returned_pending`) → `SALE_DELIVERED`.
- **Loss confirm:** requires capability `gross.loss.confirm` (see §8). Qty cap = deliverable with courier. Movement `LOSS_CONFIRM`. Never restores stock.
- **Allocation invariant:** `shipped = delivered + with_courier + lost + returned_received`.

---

## 8. Permissions

| Action | Who |
|--------|-----|
| Browse Gross (Mohra) | Mohra assignment / Manager |
| Items / shipping / customers / suppliers CRUD | Manager, Accountant (Mohra) |
| Purchase invoices create/confirm | Manager, Accountant (Mohra) — **not** Team Leader (deferred) |
| Purchase update / delete (any status) | **Manager (Admin) only** — confirmed update reverses/re-applies stock; delete unwinds returns + stock |
| Stock adjustment | Manager, Accountant |
| Create sales invoice (draft) | Manager, Accountant, Sales |
| Edit / cancel / confirm / fulfillment / COD / returns / refuse / media | **Manager (Admin) only** |
| Collect shipping fee / mark delivered | Manager, Accountant, Sales |
| Client refuse | Manager, Accountant, Sales |
| Confirm item loss | Capability `gross.loss.confirm` **and** Manager (Admin) for sales-invoice loss action |

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
Hand to courier (WITH_COURIER) with shipping_fee > 0  →  shipping_fee_status / COD = PENDING
Refuse                              →  shipping_fee_status = LOST (COD_LOST)
Collect COD                         →  PENDING → COLLECTED  (invoice status stays CONFIRMED)
Mark delivered                      →  CONFIRMED → DELIVERED
  moves remaining qty_with_courier_pieces → qty_delivered_pieces per allocation;
  stock balance WITH_COURIER − (movement SALE_DELIVERED)
  only if COD is COLLECTED (or NONE when no fee)
```

UI: مسار الشحنة queues + packing photo on sales show; deliver/refuse only after WITH_COURIER.
### 12.3 Sales invoice net totals (locked — persisted DB, T-12.2+)

Piece counts: **`gross_shipment_allocations`** (source of truth for shipped / returned / with_courier / delivered / lost).

Money after confirm (updated by `SalesInvoiceNetService` on confirm, partial return, loss, refuse):

```text
Line returned_amount = line_total × qty_returned_pieces / qty_pieces
Line lost_amount     = line_total × qty_lost_pieces / qty_pieces
Line net_line_total  = line_total − returned_amount − lost_amount

Invoice returned_merchandise = Σ returned_amount
Invoice net_subtotal         = Σ net_line_total
Invoice net_discount         = scaled from original discount (same rules as draft)
Invoice net_total            = net_subtotal − net_discount + shipping_fee
  (shipping_fee = 0 when status REFUSED)

Invoice total (original) stays immutable after confirm — net_* columns are collectible truth.
UI reads net_* + allocation columns only (SalesInvoiceDisplayService is a thin reader).
```

---

## 12. Phase 12 — Gap closure (locked before T-12.4 / T-12.5)

Build order: `docs/TASKS.md` T-12.1 → T-12.5. **Confirm §12.4 COD rules with user before coding T-12.4.**

| Task | Scope |
|------|--------|
| T-12.1 | **Low stock alert** — in-app only: portal product dashboard card + optional sidebar badge when any active item has `qty_on_hand_pieces ≤ reorder_level`. Link to stock list `?low_stock=1`. No email/SMS. |
| T-12.2 | **Shipping returns report** — per company: chronological partial returns + refuses; filters; piece totals. |
| T-12.3 | **Shipping collection statement** ✅ — route `gross.shipping.statement`; `ShippingCollectionStatementService`: merchandise owed (+), partial return netted in SALE when applicable, refuse adjustment (− merchandise only; shipping stays with courier), treasury/COD collections (−); running balance owed by courier; filters currency/from/to. Links from shipping show + index. |
| T-12.4 | **Full COD collection** ✅ — `cod_collection_status`; **Collect COD** = net merchandise from courier (shipping fee excluded); refuse → LOST; deliver gate; collections queue + invoice show; shipping fee action removed. |
| T-12.5 | **Treasury on COD** — voucher purpose `COURIER_COD`; optional auto RECEIPT on collect; no double-post per invoice. |

### 12.4 COD collection (locked — user approved plan 2026-09-01)

```text
cod_collection_status: NONE | PENDING | COLLECTED | LOST

Confirm sale + shipping_company_id + total > 0  →  PENDING
Confirm sale without shipping company OR total = 0  →  NONE
Refuse (client)                                   →  LOST (same as shipping_fee LOST path)
Collect COD                                       →  PENDING → COLLECTED (idempotent)
  Amount = invoice total at time of collection minus prior partial-return value adjustments
Mark delivered                                    →  only if cod ∈ {NONE, COLLECTED}
  (and existing stock/allocation rules unchanged)

UI: collections queue shows invoice total + COD status (replaces shipping-fee-only queue labels).
shipping_fee column stays on invoice (part of total math); separate “collect shipping fee” action removed.
Who: Collect COD — **Manager (Admin) only** (all sales-invoice mutations; see § access matrix).
```

**Migration note:** map existing `shipping_fee_status = PENDING` on CONFIRMED invoices → `cod_collection_status = PENDING`; `COLLECTED` → `COLLECTED`; `LOST` → `LOST`; `NONE` → `NONE`. Keep `shipping_fee_status` column for audit but UI/services use `cod_collection_status` after T-12.4.

### 12.5 Treasury on COD (locked)

```text
Voucher purpose: COURIER_COD (in addition to SUPPLIER | EXPENSE)
  REQUIRES sales_invoice_id + shipping_company_id
  Amount = COD collected amount (invoice total net of returns at collect time)
  One RECEIPT per invoice (unique sales_invoice_id on posted COURIER_COD receipts)

Collect COD modal:
  Manager/Accountant: checkbox “Post to treasury” (default checked), pick CASH|BANK account
  Sales: collect only — no treasury post

Auto-post creates RV-YYYYMM-#### linked to invoice; appears on shipping collection statement (−).
Collect without post: allowed; manual RECEIPT COURIER_COD later if not already posted.
```

---

## 14. Sales-person statistics (T-14.1)

Report `gross.sales.stats` (+ per-user detail). Period: month picker and `from`/`to` (default current month). Filter currency EGP|AED.

**Invoices:** status ∈ CONFIRMED|DELIVERED|REFUSED; period on `COALESCE(confirmed_at, created_at)`. Group by `sales_user_id` (null = Unassigned, Manager/Accountant only).

**KPIs (merchandise only — shipping fee excluded):**
- Net merchandise = `SUM(net_subtotal − net_discount)`
- Returned merchandise = `SUM(returned_merchandise)`
- Original merchandise = percent or amount discount off `subtotal`
- COD collected = `SUM(cod_collected_amount)` where COLLECTED
- Counts: invoices, delivered, refused, COD pending

**Who:** Manager/Accountant all; Sales own only. Product dashboard MTD cards (EGP, current month).

---

## 15. Cashier / POS create (T-14.2)

Second create path beside classic form. Entry: Sales index → **Cashier / POS** (`gross.sales.cashier`). Classic **New invoice** unchanged.

**Browse:** main category → subcategory → product cards grouped by exact item `name` (ACTIVE only; image + name + description). Tap card → modal of sibling SKUs (same `name` + `subcategory_id`) with size/color/model, price, on-hand → qty → add to cart sidebar.

**Save:** same `POST gross.sales.store` / `StoreGrossSalesInvoiceRequest` / `createDraft` as classic. JSON catalog via `CashierCatalogService` (`categories`, `subcategories`, `products`, `variants`).

---

## 16. Sales invoice media gallery (T-14.4)

Optional images on any sales invoice status (`gross_sales_invoice_media`). Title + description optional. Max 10 images / invoice, ≤5MB, jpg/jpeg/png/webp only.

**Who:** upload/delete — Manager, Accountant, Sales (`maySell`). View — anyone who can view the invoice.

**UI:** invoice show page only (`d-print-none`). Not in PDF/Excel. Separate from packing photo (مسار الشحنة).
