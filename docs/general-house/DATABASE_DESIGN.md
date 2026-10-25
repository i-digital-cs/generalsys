# General House — Database Design (Phase 0, revised)

**Status:** Phase 1 catalog tables **implemented** (`2026_10_25_200000_create_gh_catalog_tables.php`). Later sections remain design-only.  
**Prefix:** `gh_`  
**Policy:** Forward-only Laravel migrations; `docs/DATABASE_SCHEMA.md` §24.  
**Alignment:** Owner CONDITIONAL APPROVAL 2026-10-25 — see `DESIGN_CHANGE_SUMMARY.md`.

## 1. Principles

- Independent `gh_*` domain; **never** write into `gross_*` financial/ops tables.
- Do not alter payroll/attendance schemas.
- Money: `DECIMAL(12,2)`; percents `DECIMAL(5,2)`; currency `CHAR(3)` (default AED).
- Uniqueness on business references; FKs + indexes; no hardcoded production IDs.
- Issued financial rows carry **immutable snapshots**.
- Payment recording must be **idempotent** (unique natural keys where applicable).

## 2. Catalog

| Table | Purpose |
|---|---|
| `gh_service_categories` | AR/EN, description, status, sort, image |
| `gh_products` | AR/EN, **primary** `category_id`, `code`, status, sort, image |
| `gh_treatments` | AR/EN, status, sort |
| `gh_product_offerings` | `UNIQUE(product_id, treatment_id)`; `standard_price`; `tax_class`; status; sort |

**APPROVED:** Offering = unique product×treatment sellable unit.  
**APPROVED:** Orders may include offerings from **multiple categories** (via order/invoice lines).  
**PROPOSED:** Product keeps one primary category for admin navigation (not a hard limit on order composition).

## 3. Customers, zones, B2B

| Table | Purpose |
|---|---|
| `gh_customers` | `B2C`/`B2B`, names, phone, email, tax/billing fields, status, notes |
| `gh_customer_addresses` | `customer_id`, `zone_id`, street/building/apt, landmark, coords, instructions, `is_default` |
| `gh_zones` | AR/EN, `min_order_amount`, `min_order_policy` (`VALIDATION_ONLY`\|`SURCHARGE`), status |
| `gh_b2b_contracts` | `customer_id`, status, `effective_from`, `effective_to`, notes |
| `gh_b2b_contract_offerings` | `UNIQUE(contract_id, offering_id)`, `contract_price`, status |

Contract validity enforced at price resolution; expired contracts must not price new invoices. Issued lines keep frozen prices.

## 4. Orders & multi-service

| Table | Purpose |
|---|---|
| `gh_orders` | `reference` UNIQUE; customer; **pickup address**; status; pickup window (`pickup_starts_at`/`pickup_ends_at`); delivery window nullable; currency; notes |
| `gh_order_lines` *(or services)* | Optional early structure: planned category/offering hints before invoice — **PROPOSED** for multi-service visibility; invoice lines remain financial SSOT |
| `gh_order_events` | Append-only: `event_type`, `from_status`, `to_status`, `actor_id`, `occurred_at`, `recorded_at`, payload JSON |
| `gh_order_attempts` | Failed pickup/delivery attempts: `attempt_type`, `occurred_at`, `recorded_at`, `actor_id`, notes, outcome |

**APPROVED:** Schema must not force a single category per order.

## 5. Driver assignments (pickup ≠ delivery)

| Table | Purpose |
|---|---|
| `gh_order_assignments` | History rows: `order_id`, `driver_user_id` → `users.id`, `responsibility` (`PICKUP`\|`DELIVERY`), `assigned_by`, `assigned_at`, `unassigned_at` nullable, `is_current` |

Constraints / indexes (planned):

- At most one **current** assignment per `(order_id, responsibility)` (partial unique or application-enforced + check).
- Index `(driver_user_id, responsibility, is_current)`.
- No duplicate driver identities — always FK to `users`.

Current pickup/delivery drivers may also be denormalized on `gh_orders` for query speed (`pickup_driver_id`, `delivery_driver_id`) kept in sync by the assignment service.

## 6. Driver UI sort

| Table | Purpose |
|---|---|
| `gh_driver_list_orders` | `UNIQUE(driver_user_id, list_key, order_id)`, `position` — display only; never changes logistics status |

## 7. Invoicing & corrections (design)

| Table | Purpose |
|---|---|
| `gh_invoices` | `order_id`; `number` UNIQUE; status `DRAFT`\|`ISSUED`\|`VOID` (void later); money totals; tax totals; min-order fields; customer billing snapshot; currency; `issued_at` |
| `gh_invoice_lines` | Offering snapshots (product/treatment names, tax_class, unit_price); qty; line discounts; allocated invoice discount; taxable base; tax amount; line total |
| `gh_invoice_adjustments` | Invoice-level discount; **minimum-order surcharge** line; type; amount; tax_class snapshot |
| `gh_min_order_overrides` | Audit: original min, effective min, adjustment, override type, reason, actor, timestamps |
| `gh_invoice_corrections` | **Design only for v1:** links `original_invoice_id`, `correction_type` (`VOID`\|`CREDIT_NOTE`\|`REFUND`), `correction_invoice_id` nullable, reason, actor — **no silent edit of ISSUED** |

**APPROVED v1:** One **primary** operational invoice per order — enforce with `UNIQUE` on `order_id` where `status IN (DRAFT,ISSUED)` or single open primary flag (exact constraint chosen at migration time).  
**APPROVED:** ISSUE is transaction-safe and idempotent.  
**REQUIRES OWNER CONFIRMATION:** Full void/credit/refund tax & numbering rules before implementing correction workflows.

## 8. Payments (GH ledger only)

| Table | Purpose |
|---|---|
| `gh_payments` | `invoice_id`, method `CASH`\|`ONLINE`, amount, status, `collector_user_id`, `external_ref`, `idempotency_key` UNIQUE, `occurred_at`, `recorded_at` |
| `gh_payment_allocations` | Optional split across invoices (v1 may allocate 1:1) |

**APPROVED:** Do **not** insert into Gross treasury/voucher tables.  
Indexes: `(invoice_id, status)`, unique `idempotency_key`, unique `(invoice_id, external_ref)` where not null for online.

## 9. Sequences & ACL

| Table | Purpose |
|---|---|
| `gh_sequences` | Named counters (orders, invoices, corrections) |
| `gh_user_pages` | Non-Manager page ACL (Gross-like) |

## 10. Referential integrity (summary)

- All FKs to `users`, `gh_customers`, `gh_zones`, offerings, orders, invoices as appropriate; prefer `RESTRICT`/`SET NULL` deliberately (no cascade-delete of issued money).
- Deleting catalog rows blocked if referenced by issued snapshots (or soft-deactivate only).

## 11. Planned indexes (non-exhaustive)

- `gh_orders (status, pickup_starts_at)` — eligibility job  
- `gh_orders (pickup_driver_id, status)`, `(delivery_driver_id, status)`  
- `gh_order_assignments (order_id, responsibility, is_current)`  
- `gh_product_offerings (product_id, treatment_id)` UNIQUE  
- `gh_invoices (number)` UNIQUE; primary-per-order uniqueness  
- `gh_payments (idempotency_key)` UNIQUE  

## 12. Migration safety

- No `migrate:fresh` on shared DB; no Gross/HR destructive changes; no production data wipes.
