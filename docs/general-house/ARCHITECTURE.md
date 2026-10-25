# General House — Architecture (Phase 0, revised)

**Status:** Phase 0 design + **Phase 1 catalog implemented** (2026-10-25).  
**Alignment:** Owner CONDITIONAL APPROVAL 2026-10-25 — see `DESIGN_CHANGE_SUMMARY.md`.

## 1. Placement (APPROVED)

```text
GeneralSys
├── HR (unchanged)
├── Mohra / product + Gross ERP (unchanged)
├── Settings (unchanged)
└── General house / service
    ├── Valuation (existing — preserved)
    └── Operations (NEW) ← this program (gh_*)
```

- Remains under existing **`service`** module — **not** a separate application.
- Independent **`gh_*`** database domain; **no** Gross financial/ops table reuse.
- Reuse architectural patterns (Form Requests, Policies, sequences, DECIMAL money, Hostinger constraints).

**Routes (Phase 1):** `/service/gh/...` named `gh.*`  
**Code layout (Phase 1):**

| Layer | Path |
|---|---|
| Services | `app/Services/GeneralHouse/Catalog/*` |
| Models | `app/Models/GeneralHouse/Gh*` |
| Policies | `app/Policies/GeneralHouse/Gh*` |
| Views | `resources/views/gh/...` |
| Lang | `lang/{en,ar}/gh.php` |
| Access | `GhCatalogAccess` — Manager-only manage (Phase 1) |

## 2. Layering (APPROVED pattern)

```text
HTTP (Form Request → Policy → Controller)
        ↓
Domain Services
  Catalog | Customers | Pricing | Orders | Assignments
  Logistics | Invoicing | Payments | Reporting
        ↓
Eloquent models (thin) + DB transactions
```

Controllers: validate, authorize, call service, return view/redirect.  
**No** pricing, tax, or status rules in Blade/JS/controllers.

## 3. Authoritative engines (APPROVED SSOT intent)

| Concern | SSOT class (PROPOSED names) |
|---|---|
| Order status transitions | `LogisticsTransitionService` |
| Pickup / delivery assignment | `DriverAssignmentService` |
| Invoice money (full pipeline) | `InvoiceCalculationEngine` |
| Catalog / B2B price resolution | `PricingResolver` |
| Pickup eligibility (T−24h) | `PickupEligibilityService` + scheduled command |
| Payments (GH ledger) | `GhPaymentService` |

## 4. Catalog & orders

**Phase 1 shipped:** Categories → Products → Treatments → Offerings (`UNIQUE` product×treatment), standard AED DECIMAL prices, tax_class metadata, ACTIVE/INACTIVE, sort_order, AR/EN names. Sellable query: `GhSellableCatalogService`.

**Phase 2 shipped:** Zones (min-order amount/policy metadata), customers B2C/B2B, addresses (one default via `default_for_customer_id` unique), B2B contracts + contract offerings (DECIMAL prices; do not mutate standard prices). `GhPricingResolver`: B2C=standard sellable set; B2B=active contract offerings only; inactive catalog chain excluded.

**Later phases:** Orders; logistics; invoice VAT / min-order surcharge math; payments.

## 5. Logistics & drivers (APPROVED revision)

- Primary states: `LATER → PICKUP → WORKING → DELIVERY → COMPLETED`.
- Pickup and delivery drivers **independently** assignable; full history with `responsibility`.
- Drivers = existing `users` + DRIVER permissions — no duplicate identity tables.
- Failed attempts / reassignment / reschedule = **events** (and attempt rows), not unnecessary primary states.
- Payment status independent of logistics.

Details: `STATE_MACHINE.md`.

## 6. Finance (APPROVED defaults; confirmations remaining)

- AED, tax-exclusive, default 5% VAT, HALF_UP, DECIMAL only.
- Tax classes: STANDARD_RATED | ZERO_RATED | EXEMPT | OUT_OF_SCOPE (rules-based).
- Discount → min-order → VAT pipeline in one server engine.
- Zone min-order: VALIDATION_ONLY | SURCHARGE (default SURCHARGE) + authorized overrides.
- One primary invoice per order; ISSUED immutable; corrections designed, not built until approved.
- GH payment ledger only — never write into Gross treasury.

Details: `FINANCIAL_RULES.md`. Blocking: `C-GH-TAX-MIN`, `C-GH-CORR`, `C-GH-CANCEL`, `C-GH-MULTI-CUR`.

## 7. Patterns borrowed from Gross

- Sequence tables for human references  
- Label/price snapshots on invoice lines  
- Idempotent transitions + payment idempotency keys  
- Page-level ACL for non-Manager staff (**PROPOSED** `gh_user_pages`)  
- DECIMAL(12,2) money; never float  

## 8. Patterns not borrowed from Gross

- Stock PIECE ledger / carton conversion  
- Courier-company fulfillment states  
- `gross_customers` / Gross vouchers as laundry CRM or cash store  

## 9. Hosting

- Artisan commands under existing `schedule:run` (eligibility catch-up).  
- Heavy reports: chunked / queued (`database` driver).  
- Assets: Vite build locally; upload `public/build`.  
- No Redis/Supervisor/Node-on-server requirements.

## 10. Security

- Every mutating route: Form Request + Policy.  
- Drivers scoped to assigned pickup/delivery orders (IDOR tests mandatory).  
- Min-order / discount / tax-class overrides: explicit permissions + audit rows.  
- No silent edit of ISSUED invoices or successful payments.
