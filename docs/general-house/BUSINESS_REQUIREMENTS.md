# General House — Business Requirements (Phase 0)

Derived from owner brief; refined against existing GeneralSys capabilities.

## 1. Goals

Deliver laundry/service **operations + invoicing + logistics** inside GeneralSys under the **General house** (`service`) module, without breaking HR, Mohra Gross, or valuation.

## 2. Actors

| Actor | Needs |
|---|---|
| Manager / Admin | Full catalog, customers, contracts, overrides, finance, reports |
| Dispatcher / ops staff | Orders, assign drivers, schedule, invoice drafts |
| Accountant | Invoices, payments, outstanding, tax reports (confirm) |
| Driver (`DRIVER` role — proposed) | Assigned pickups/deliveries; confirm pickup/delivery; no unauthorized money |
| Customer (external) | Not a portal login in v1 (staff-managed) |

## 3. Functional epics (phases)

1. Dynamic catalog (categories, products, treatments, offerings, prices)
2. Customers B2C/B2B, addresses, zones, min order, contracts
3. Order creation, scheduling, driver assignment, history
4. Logistics state machine + 24h eligibility job
5. Invoice builder + financial engine + snapshots
6. Delivery ops + payment recording
7. Driver workspace (mobile-first, drag order)
8. Dashboard & reporting

## 4. Non-functional

- Hostinger-safe (no Redis; DB queue; single cron)
- AR/EN, RTL/LTR, responsive
- Policies server-side; audit sensitive actions
- Decimal money; immutable issued invoices
- Forward-only migrations; no payroll/attendance mutation

## 5. Explicit non-goals (v1 unless owner expands)

- Customer self-service portal
- Merging Mohra stock/catalog into laundry
- Automatic LIVE deploy from git push
- Inventing tax law beyond configured rates
- Replacing valuation sheets

## 6. Success criteria

Each phase acceptance list from owner brief §§3–10; all require automated tests + UI/UX gate for Blade work.
